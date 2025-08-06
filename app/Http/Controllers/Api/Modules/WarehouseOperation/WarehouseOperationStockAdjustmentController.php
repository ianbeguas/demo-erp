<?php

namespace App\Http\Controllers\Api\Modules\WarehouseOperation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WarehouseStockAdjustment;
use App\Models\WarehouseProduct;
use App\Models\WarehouseProductSerial;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class WarehouseOperationStockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $query = WarehouseStockAdjustment::with([
            'warehouse',
            'warehouseProduct.supplierProductDetail.product',
            'adjustedByUser'
        ])->latest();

        if ($request->has('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->has('warehouse_product_id')) {
            $query->where('warehouse_product_id', $request->warehouse_product_id);
        }

        return $query->paginate(10);
    }


public function store(Request $request)
{
    $validated = $request->validate([
        'warehouse_id' => 'required|exists:warehouses,id',
        'warehouse_product_id' => 'required|exists:warehouse_products,id',
        'system_quantity' => 'required|integer|min:0',
        'actual_quantity' => 'required|integer|min:0',
        'adjustment' => 'required|integer',
        'reason' => 'required|in:damaged,lost,count-correction,other,add-stock,found',
        'remarks' => 'nullable|string|max:1024',
        'serials' => 'nullable|array',
        'serials.*.serial_number' => 'required_with:serials|string',
        'serials.*.manufactured_date' => 'nullable|date',
        'serials.*.expiry_date' => 'nullable|date',
    ]);

    try {
        DB::beginTransaction();

        // Create Adjustment Record
        $adjustment = WarehouseStockAdjustment::create([
            ...$validated,
            'adjusted_at' => now(),
            'adjusted_by_user_id' => Auth::id(),
        ]);

        // Adjust Warehouse Product Quantity based on Reason
        $warehouseProduct = WarehouseProduct::findOrFail($request->warehouse_product_id);

        switch ($request->reason) {
            case 'add-stock':
            case 'found':
                $warehouseProduct->qty += $request->adjustment;
                break;

            case 'damaged':
            case 'lost':
                $warehouseProduct->qty -= $request->adjustment;
                break;

            case 'count-correction':
            case 'other':
                $warehouseProduct->qty = $request->actual_quantity;
                break;
        }

        $warehouseProduct->save();

        // Save Serials if Add Stock
        if ($request->reason === 'add-stock' && $request->has('serials')) {
            foreach ($request->serials as $serialData) {
                WarehouseProductSerial::create([
                    'warehouse_product_id' => $warehouseProduct->id,
                    'serial_number' => $serialData['serial_number'],
                    'batch_number' => null,
                    'manufactured_at' => $serialData['manufactured_date'],
                    'expired_at' => $serialData['expiry_date'],
                    'is_sold' => false,
                ]);
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Stock adjustment created successfully',
            'data' => $adjustment->load(['warehouse', 'warehouseProduct.supplierProductDetail.product', 'adjustedByUser'])
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'message' => 'Failed to create stock adjustment',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function validateSerial(Request $request)
{
    $request->validate([
        'warehouse_product_id' => 'required|exists:warehouse_products,id',
        'serial_number' => 'required|string',
    ]);

    $serial = WarehouseProductSerial::where('warehouse_product_id', $request->warehouse_product_id)
        ->where('serial_number', $request->serial_number)
        ->where('is_sold', false)
        ->first();

    if (!$serial) {
        return response()->json([
            'valid' => false,
            'message' => 'Serial number not found or already sold'
        ], 404);
    }

    return response()->json([
        'valid' => true,
        'data' => $serial
    ]);
}
public function autocompleteSerials(Request $request)
{
    $request->validate(['search' => 'required|string|min:1']);

    $serials = WarehouseProductSerial::with('warehouseProduct.supplierProductDetail.product')
        ->where('serial_number', 'like', "%{$request->search}%")
        ->where('is_sold', false)
        ->take(10)
        ->get()
        ->map(function($serial) {
            return [
                'id' => $serial->id,
                'serial_number' => $serial->serial_number,
                'product_name' => $serial->warehouseProduct->supplierProductDetail->product->name,
                'warehouse_product_id' => $serial->warehouse_product_id
            ];
        });

    return response()->json($serials);
}
    public function show($id)
    {
        $adjustment = WarehouseStockAdjustment::with([
            'warehouse',
            'warehouseProduct.supplierProductDetail.product',
            'adjustedByUser'
        ])->findOrFail($id);

        return response()->json($adjustment);
    }

    public function destroy($id)
    {
        $adjustment = WarehouseStockAdjustment::findOrFail($id);
        $adjustment->delete();

        return response()->json(['message' => 'Stock adjustment deleted successfully']);
    }

    public function autocomplete(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1',
        ]);

        $query = WarehouseStockAdjustment::with(['warehouse', 'warehouseProduct.supplierProductDetail.product', 'adjustedByUser'])
            ->where(function ($q) use ($request) {
                $q->whereHas('warehouse', function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%");
                })
                    ->orWhereHas('warehouseProduct.supplierProductDetail.product', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%");
                    });
            })
            ->take(10)
            ->get();

        return response()->json([
            'data' => $query,
            'message' => 'Stock adjustments retrieved successfully'
        ]);
    }
    public function autocompleteProducts(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1',
        ]);

        $products = WarehouseProduct::with('supplierProductDetail.product')
            ->whereHas('supplierProductDetail.product', function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%");
            })
            ->take(10)
            ->get();

        return response()->json($products);
    }
}
