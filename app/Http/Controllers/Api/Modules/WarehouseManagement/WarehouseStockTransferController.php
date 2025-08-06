<?php

namespace App\Http\Controllers\Api\Modules\WarehouseManagement;

use App\Events\LowStockDetected;
use App\Http\Controllers\Controller;
use App\Models\StockAlertThreshold;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\WarehouseStockTransfer;
use App\Models\WarehouseStockTransferDetail;
use App\Models\WarehouseStockTransferSerial;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Models\WarehouseProductSerial;
use App\Notifications\LowStockNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Route;

class WarehouseStockTransferController extends Controller
{
    public function productsForTransfer()
    {
        try {
            $user = Auth::user();

            // 🛠 FIXED ambiguous column error
            $warehouseIds = $user->warehouses()->pluck('warehouses.id');

            if ($warehouseIds->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No warehouses assigned.'
                ]);
            }

            $products = WarehouseProduct::with([
                'warehouse',
                'supplierProductDetail.product',
                'supplierProductDetail.supplier'
            ])
                ->whereIn('warehouse_id', $warehouseIds)
                ->whereHas('supplierProductDetail.product')
                ->whereHas('supplierProductDetail.supplier')
                ->get();
            //     \Log::info('Fetched products count:', ['count' => $products->count()]);
            //     \Log::error('User id:', [
            //     'user_id' => Auth::id()
            // ]);

            return response()->json([
                'data' => $products,
                'message' => 'Products fetched successfully.'
            ]);
        } catch (\Throwable $e) {
            \Log::error('❌ Stock transfer fetch error', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function simpleTransfer(Request $request)
    {

        $validated = $request->validate([
            'destination_warehouse_id' => 'required|exists:warehouses,id',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:warehouse_products,id',
            'products.*.qty' => 'required|integer|min:1',
        ]);
        Log::info('🚚 Incoming Transfer Request', $validated);


        try {
            DB::beginTransaction();

            $user = Auth::user();
            $destinationId = $validated['destination_warehouse_id'];
            $productInputs = collect($validated['products']);

            $productIds = $productInputs->pluck('id');
            $products = WarehouseProduct::whereIn('id', $productIds)
                ->with(['supplierProductDetail.product', 'warehouse'])
                ->get();

            $zeroQtyProducts = $products->filter(fn($p) => $p->qty <= 0);
            if ($zeroQtyProducts->isNotEmpty()) {
                $names = $zeroQtyProducts->map(
                    fn($p) =>
                    optional($p->supplierProductDetail->product)->name . ' (Warehouse: ' . optional($p->warehouse)->name . ')'
                )->implode(', ');

                return response()->json([
                    'message' => 'Transfer aborted. Some products have zero quantity.',
                    'details' => $names
                ], 422);
            }

            if ($products->every(fn($p) => $p->warehouse_id == $destinationId)) {
                return response()->json([
                    'message' => 'Transfer aborted. All selected products belong to the destination warehouse.',
                ], 422);
            }

            $groupedProducts = $products->groupBy('warehouse_id');
            $lastTransfer = null;

            foreach ($groupedProducts as $originWarehouseId => $originProducts) {
                if ($originWarehouseId == $destinationId) continue;

                $transfer = WarehouseStockTransfer::create([
                    'origin_warehouse_id' => $originWarehouseId,
                    'destination_warehouse_id' => $destinationId,
                    'transfer_date' => now(),
                    'status' => 'pending', // Awaiting serial scan or manual qty confirmation
                    'created_by_user_id' => $user->id,
                ]);

                foreach ($originProducts as $product) {
                    $inputQty = $productInputs->firstWhere('id', $product->id)['qty'];

                    if ($inputQty > $product->qty) {
                        return response()->json([
                            'message' => "Insufficient stock for product: " . optional($product->supplierProductDetail->product)->name,
                        ], 422);
                    }

                    $destinationProduct = WarehouseProduct::firstOrCreate(
                        [
                            'warehouse_id' => $destinationId,
                            'supplier_product_detail_id' => $product->supplier_product_detail_id
                        ],
                        [
                            'sku' => $product->sku,
                            'barcode' => $product->barcode,
                            'qty' => 0,
                            'price' => $product->price,
                            'last_cost' => $product->last_cost,
                            'average_cost' => $product->average_cost,
                            'critical_level_qty' => $product->critical_level_qty,
                            'has_serials' => $product->has_serials,
                        ]
                    );

                    WarehouseStockTransferDetail::create([
                        'warehouse_stock_transfer_id' => $transfer->id,
                        'origin_warehouse_product_id' => $product->id,
                        'destination_warehouse_product_id' => $destinationProduct->id,
                        'expected_qty' => $inputQty,
                        'transferred_qty' => 0, // Will be updated after scanning/completion
                    ]);
                }

                $lastTransfer = $transfer;
            }

            DB::commit();

            return response()->json([
                'message' => 'Transfer(s) created successfully.',
                'redirect_url' => route('warehouse.transfer.scan', ['transfer' => $lastTransfer->id]),
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('❌ Grouped simple transfer failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Transfer failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function completeTransfer(Request $request, WarehouseStockTransfer $transfer)
    {
        $validated = $request->validate([
            'scanned_serials' => 'array',
            'manually_completed_details' => 'array',
        ]);

        $scannedSerials = collect($validated['scanned_serials'] ?? []);
        $manuallyCompletedDetails = collect($validated['manually_completed_details'] ?? []);

        \Log::info('🔍 Transfer Completion Incoming Data', [
            'transfer_id' => $transfer->id,
            'scanned_serials' => $scannedSerials,
            'manually_completed_details' => $manuallyCompletedDetails,
        ]);

        $transfer->load([
            'details.destinationProduct.serials',
            'details.originProduct.serials',
            'details.destinationProduct',
            'details.originProduct'
        ]);

        // Check for duplicate serials globally
        if ($scannedSerials->duplicates()->isNotEmpty()) {
            return response()->json([
                'message' => 'Duplicate serial numbers detected in scanned serials.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($transfer->details as $detail) {
                $destinationProduct = $detail->destinationProduct;
                $originProduct = $detail->originProduct;

                if ($destinationProduct->has_serials) {
                    $expectedSerials = $originProduct->serials->pluck('serial_number');
                    $matchedSerials = $scannedSerials->intersect($expectedSerials);

                    \Log::info('Serial Matching', [
                        'detail_id' => $detail->id,
                        'expected_serials' => $expectedSerials,
                        'scanned_serials' => $scannedSerials,
                        'matched_serials' => $matchedSerials,
                    ]);

                    // Validate matched serials == expected_qty
                    if ($matchedSerials->count() !== $detail->expected_qty) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Serials for product '{$originProduct->name}' do not match expected quantity.",
                            'expected_qty' => $detail->expected_qty,
                            'matched_qty' => $matchedSerials->count(),
                        ], 422);
                    }

                    // Update Transfer Detail
                    $detail->update(['transferred_qty' => $matchedSerials->count()]);

                    // Adjust Stock & Transfer Serial Ownership
                    $destinationProduct->increment('qty', $matchedSerials->count());
                    $originProduct->decrement('qty', $matchedSerials->count());

                    foreach ($matchedSerials as $serialNumber) {
                        $serial = $originProduct->serials()->where('serial_number', $serialNumber)->first();
                        if ($serial) {
                            $serial->warehouse_product_id = $destinationProduct->id;
                            $serial->save();
                        }
                    }
                } else {
                    // Non-serial Products Validation
                    if (!$manuallyCompletedDetails->contains($detail->id)) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Non-serial product '{$originProduct->name}' is not marked as completed."
                        ], 422);
                    }

                    $detail->update(['transferred_qty' => $detail->expected_qty]);
                    $destinationProduct->increment('qty', $detail->expected_qty);
                    $originProduct->decrement('qty', $detail->expected_qty);
                }
            }

            $transfer->update(['status' => 'completed']);

            DB::commit();

            return response()->json(['message' => 'Transfer completed successfully.']);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('❌ Transfer Completion Failed', [
                'transfer_id' => $transfer->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Failed to complete transfer.'], 500);
        }
    }
   public function searchProductsBySerials(Request $request)
{
    $request->validate([
        'serials' => 'required|array|min:1',
        'serials.*' => 'string'
    ]);

    try {
        $user = Auth::user();

        $warehouseIds = $user->warehouses()->pluck('warehouses.id');

        if ($warehouseIds->isEmpty()) {
            return response()->json(['data' => [], 'message' => 'No warehouses assigned.']);
        }

        $serials = $request->serials;

        $matchedSerials = \App\Models\WarehouseProductSerial::with([
                'warehouseProduct.warehouse',
                'warehouseProduct.supplierProductDetail.product'
            ])
            ->whereIn('serial_number', $serials)
            ->whereNull('deleted_at')       // Ensure active
            ->where('is_sold', 0)           // Ensure unsold/available
            ->whereHas('warehouseProduct', function ($query) use ($warehouseIds) {
                $query->whereIn('warehouse_id', $warehouseIds);
            })
            ->get();

        if ($matchedSerials->isEmpty()) {
            return response()->json(['data' => [], 'message' => 'No valid serials found.']);
        }

        // Group serials by warehouse_product_id
        $products = $matchedSerials->groupBy('warehouse_product_id')->map(function ($serialGroup) {
            $warehouseProduct = $serialGroup->first()->warehouseProduct;
            $warehouseProduct->matched_serials = $serialGroup->pluck('serial_number');
            return $warehouseProduct;
        })->values();

        return response()->json(['data' => $products, 'message' => 'Products fetched successfully.']);
    } catch (\Throwable $e) {
        \Log::error('❌ Serial search error', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage()
        ]);
        return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
    }
}

    public function index(Request $request)
    {
        $query = WarehouseStockTransfer::with([
            'originWarehouse',
            'originWarehouse.company',
            'destinationWarehouse',
            'createdByUser'
        ])->latest();

        if ($request->has('warehouse_id')) {
            $query->where(function ($q) use ($request) {
                $q->where('origin_warehouse_id', $request->warehouse_id)
                    ->orWhere('destination_warehouse_id', $request->warehouse_id);
            });
        }

        return $query->paginate(10);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'origin_warehouse_id' => 'required|exists:warehouses,id',
            'destination_warehouse_id' => 'required|exists:warehouses,id|different:origin_warehouse_id',
            'transfer_date' => 'required|date',
            'details' => 'required|array',
            'details.*.origin_warehouse_product_id' => 'required|exists:warehouse_products,id',
            'details.*.expected_qty' => 'required|integer|min:1',
            'details.*.serials' => 'array',
            'details.*.serials.*.serial_number' => 'required_if:details.*.has_serials,true|string',
            'details.*.serials.*.batch_number' => 'nullable|string',
            'details.*.serials.*.manufactured_at' => 'nullable|date',
            'details.*.serials.*.expired_at' => 'nullable|date|after:manufactured_at',
        ]);

        try {
            DB::beginTransaction();

            // Create the transfer record
            $transfer = WarehouseStockTransfer::create([
                'origin_warehouse_id' => $validated['origin_warehouse_id'],
                'destination_warehouse_id' => $validated['destination_warehouse_id'],
                'transfer_date' => $validated['transfer_date'],
                'status' => 'pending',
                'created_by_user_id' => Auth::id()
            ]);

            // Process each detail
            foreach ($validated['details'] as $detail) {
                $originProduct = WarehouseProduct::findOrFail($detail['origin_warehouse_product_id']);

                // Create or get destination warehouse product
                $destinationProduct = WarehouseProduct::firstOrCreate(
                    [
                        'warehouse_id' => $validated['destination_warehouse_id'],
                        'supplier_product_detail_id' => $originProduct->supplier_product_detail_id,
                    ],
                    [
                        'sku' => $originProduct->sku,
                        'barcode' => $originProduct->barcode,
                        'critical_level_qty' => $originProduct->critical_level_qty,
                        'qty' => 0,
                        'price' => $originProduct->price,
                        'last_cost' => $originProduct->last_cost,
                        'average_cost' => $originProduct->average_cost,
                        'has_serials' => $originProduct->has_serials,
                    ]
                );

                // Create transfer detail
                $transferDetail = WarehouseStockTransferDetail::create([
                    'warehouse_stock_transfer_id' => $transfer->id,
                    'origin_warehouse_product_id' => $originProduct->id,
                    'destination_warehouse_product_id' => $destinationProduct->id,
                    'expected_qty' => $detail['expected_qty'],
                    'transferred_qty' => 0,
                ]);

                // Process serials if any
                if (!empty($detail['serials'])) {
                    foreach ($detail['serials'] as $serial) {
                        WarehouseStockTransferSerial::create([
                            'warehouse_stock_transfer_id' => $transfer->id,
                            'warehouse_stock_transfer_detail_id' => $transferDetail->id,
                            'serial_number' => $serial['serial_number'],
                            'batch_number' => $serial['batch_number'] ?? null,
                            'manufactured_at' => $serial['manufactured_at'] ?? null,
                            'expired_at' => $serial['expired_at'] ?? null,
                            'is_sold' => false,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Stock transfer created successfully',
                'data' => $transfer->load([
                    'originWarehouse',
                    'destinationWarehouse',
                    'createdByUser',
                    'details',
                    'details.serials'
                ])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create stock transfer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function validateSerial(Request $request)
    {
        $request->validate([
            'warehouse_stock_transfer_id' => 'required|exists:warehouse_stock_transfers,id',
            'warehouse_product_id' => 'required|exists:warehouse_products,id',
            'serial_number' => 'required|string',
        ]);

        try {
            // Get the transfer and check its status
            $transfer = WarehouseStockTransfer::findOrFail($request->warehouse_stock_transfer_id);

            if (!in_array($transfer->status, ['approved', 'partially_received'])) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Transfer must be approved before receiving items'
                ], 400);
            }

            // First check if the serial exists in the origin warehouse
            $serial = WarehouseProductSerial::where('warehouse_product_id', $request->warehouse_product_id)
                ->where('serial_number', $request->serial_number)
                ->first();

            if (!$serial) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Serial number not found in origin warehouse'
                ], 404);
            }

            if ($serial->is_sold) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Serial number is already sold'
                ], 400);
            }

            // Check if this serial is part of the transfer
            $transferSerial = WarehouseStockTransferSerial::where('warehouse_stock_transfer_id', $request->warehouse_stock_transfer_id)
                ->where('serial_number', $request->serial_number)
                ->first();

            if (!$transferSerial) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Serial number is not part of this transfer'
                ], 400);
            }

            // Check if serial is already in another active transfer
            $existingTransfer = WarehouseStockTransferSerial::whereHas('transfer', function ($q) use ($request) {
                $q->whereIn('status', ['pending', 'approved', 'partially_received'])
                    ->where('id', '!=', $request->warehouse_stock_transfer_id);
            })->where('serial_number', $request->serial_number)
                ->first();

            if ($existingTransfer) {
                return response()->json([
                    'valid' => false,
                    'message' => 'Serial number is already in another active transfer'
                ], 400);
            }

            return response()->json([
                'valid' => true,
                'data' => $serial
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'message' => 'Error validating serial: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $transfer = WarehouseStockTransfer::with([
            'originWarehouse',
            'originWarehouse.company',
            'destinationWarehouse',
            'createdByUser',
            'details',
            'details.serials'
        ])->findOrFail($id);

        return response()->json($transfer);
    }

    public function destroy($id)
    {
        $transfer = WarehouseStockTransfer::findOrFail($id);
        $transfer->delete();

        return response()->json(['message' => 'Stock transfer deleted successfully']);
    }

    public function autocomplete(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1',
        ]);

        $query = WarehouseStockTransfer::with([
            'originWarehouse',
            'originWarehouseProduct.supplierProductDetail.product',
            'destinationWarehouse',
            'destinationWarehouseProduct.supplierProductDetail.product',
            'createdByUser'
        ])
            ->where(function ($q) use ($request) {
                $q->whereHas('originWarehouse', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%");
                })
                    ->orWhereHas('destinationWarehouse', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%");
                    })
                    ->orWhereHas('originWarehouseProduct.supplierProductDetail.product', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%");
                    });
            })
            ->take(10)
            ->get();

        return response()->json([
            'data' => $query,
            'message' => 'Stock transfers retrieved successfully'
        ]);
    }

    public function approve($id)
    {
        $transfer = WarehouseStockTransfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'Stock transfer cannot be approved in its current state'
            ], 400);
        }

        $transfer->update([
            'status' => 'approved',
            'remarks' => request('notes')
        ]);

        return response()->json([
            'message' => 'Stock transfer approved successfully',
            'data' => $transfer
        ]);
    }

    public function reject($id)
    {
        $transfer = WarehouseStockTransfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'Stock transfer cannot be rejected in its current state'
            ], 400);
        }

        $transfer->update([
            'status' => 'rejected',
            'remarks' => request('notes')
        ]);

        return response()->json([
            'message' => 'Stock transfer rejected successfully',
            'data' => $transfer
        ]);
    }

    public function cancel($id)
    {
        $transfer = WarehouseStockTransfer::findOrFail($id);

        if ($transfer->status !== 'pending') {
            return response()->json([
                'message' => 'Stock transfer cannot be cancelled in its current state'
            ], 400);
        }

        $transfer->update([
            'status' => 'cancelled',
            'remarks' => request('notes')
        ]);

        return response()->json([
            'message' => 'Stock transfer cancelled successfully',
            'data' => $transfer
        ]);
    }
    public function complete($id)
    {
        $transfer = WarehouseStockTransfer::with([
            'details.originWarehouseProduct.supplierProductDetail.product',
            'details.destinationWarehouseProduct',
            'details.serials'
        ])->findOrFail($id);

        if ($transfer->status !== 'fully-transferred') {
            return response()->json([
                'message' => 'Transfer must be fully transferred before completing'
            ], 400);
        }

        try {
            DB::beginTransaction();

            foreach ($transfer->details as $detail) {
                $originProduct = $detail->originWarehouseProduct;
                $originProduct->decrement('qty', $detail->transferred_qty);

                $threshold = StockAlertThreshold::where('warehouse_id', $originProduct->warehouse_id)
                    ->where('product_id', $originProduct->supplierProductDetail->product_id)
                    ->first();

                $newQty = $originProduct->fresh()->qty;

                \Log::info('Checking stock threshold', [
                    'product_id' => $originProduct->supplierProductDetail->product_id,
                    'product_name' => $originProduct->supplierProductDetail->product->name,
                    'warehouse_id' => $originProduct->warehouse_id,
                    'remaining_qty' => $newQty,
                    'min_qty' => optional($threshold)->min_qty
                ]);

                if ($threshold && $newQty <= $threshold->min_qty) {
                    // Notify all super-admins (custom check if no role system)
                    $admins = User::role('super-admin')->get();  // Adjust this to your structure
                    foreach ($admins as $admin) {
                        $admin->notify(new \App\Notifications\LowStockNotification(
                            $originProduct->supplierProductDetail->product,
                            $originProduct->warehouse,
                            $newQty
                        ));
                    }

                    \Log::info('📦 Low stock notification sent', [
                        'product' => $originProduct->supplierProductDetail->product->name,
                        'warehouse' => $originProduct->warehouse->name,
                        'remaining_qty' => $newQty
                    ]);
                }

                $destinationProduct = $detail->destinationWarehouseProduct;
                $destinationProduct->increment('qty', $detail->transferred_qty);

                if ($originProduct->has_serials) {
                    foreach ($detail->serials as $serial) {
                        WarehouseProductSerial::where('warehouse_product_id', $originProduct->id)
                            ->where('serial_number', $serial->serial_number)
                            ->update([
                                'warehouse_product_id' => $destinationProduct->id
                            ]);
                    }
                }
            }

            $transfer->update(['status' => 'completed']);
            DB::commit();

            return response()->json([
                'message' => 'Stock transfer completed successfully',
                'data' => $transfer->fresh()->load([
                    'originWarehouse',
                    'destinationWarehouse',
                    'details',
                    'details.serials'
                ])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('❌ Stock transfer failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Failed to complete stock transfer: ' . $e->getMessage()
            ], 500);
        }
    }
}
