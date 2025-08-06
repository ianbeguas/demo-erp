<?php

namespace App\Http\Controllers\Api\Modules\WarehouseManagement;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierProductDetail;
use App\Models\Warehouse;
use App\Models\WarehouseProduct;
use App\Models\WarehouseStockAdjustment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $warehousesQuery = Warehouse::query();

        // Filter by warehouse ID
        if ($request->filled('warehouse')) {
            $warehousesQuery->where('id', $request->warehouse);
        }

        // Eager load products with filters and stock adjustments
        $warehouses = $warehousesQuery->with(['products' => function ($query) use ($request) {
            $query->with([
                'supplierProductDetail.product.category',
                'supplierProductDetail.variation',
                'supplierProductDetail.supplier',
                'stockAdjustments' => function ($q) {
                    $q->whereIn('reason', ['damaged', 'lost']);
                },
            ]);

            // Apply filters inside the nested products
            if ($request->filled('product')) {
                $query->whereHas('supplierProductDetail.product', function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->product . '%');
                });
            }

            if ($request->filled('supplier')) {
                $query->whereHas('supplierProductDetail.supplier', function ($q) use ($request) {
                    $q->where('id', $request->supplier);
                });
            }

            if ($request->filled('min_price')) {
                $query->where('price', '>=', (float) $request->min_price);
            }

            if ($request->filled('max_price')) {
                $query->where('price', '<=', (float) $request->max_price);
            }
        }])->get();

        return response()->json([
            'data' => $warehouses,
            'message' => 'Inventory data retrieved successfully.',
        ]);
    }

    public function getWarehouses()
    {
        $warehouses = Warehouse::select('id', 'name')->get();
        return response()->json($warehouses);
    }

    public function getSuppliers()
    {
        $suppliers = Supplier::select('id', 'name')->get();
        return response()->json($suppliers);
    }
    public function getCategories()
    {
        $categories = Category::select('id', 'name')->get();
        return response()->json($categories);
    }
    public function analytics(Request $request)
    {
        $query = WarehouseProduct::query()->with('supplierProductDetail.product.category');

        // Apply filters
        if ($request->filled('warehouse')) {
            $query->where('warehouse_id', $request->warehouse);
        }

        if ($request->filled('supplier')) {
            $query->whereHas('supplierProductDetail.supplier', function ($q) use ($request) {
                $q->where('id', $request->supplier);
            });
        }

        if ($request->filled('product')) {
            $query->whereHas('supplierProductDetail.product', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->product . '%');
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        $products = $query->get();

        // Count total items
        $totalItems = $products->count();

        // Total value
        $totalValue = $products->sum(function ($item) {
            return ($item->price ?? 0) * ($item->qty ?? 0);
        });

        // Low stock
        // $lowStock = $products->filter(function ($item) {
        //     return $item->qty <= $item->critical_level_qty;
        // })->count();
        $lowStock = $products->filter(function ($item) {
            return $item->critical_level_qty > 0 && $item->qty <= $item->critical_level_qty;
        })->count();

        // Damaged items (filter by warehouse if applicable)
        $damagedQuery = WarehouseStockAdjustment::whereIn('reason', ['damaged', 'lost']);

        if ($request->filled('warehouse')) {
            $damagedQuery->where('warehouse_id', $request->warehouse);
        }
        $damaged = $damagedQuery->count();

        // Unique categories from fetched products
        $categories = $products
            ->pluck('supplierProductDetail.product.category.name')
            ->filter()
            ->unique()
            ->count();

        return response()->json([
            'total_items' => $totalItems,
            'total_value' => $totalValue,
            'low_stock' => $lowStock,
            'damaged' => $damaged,
            'categories' => $categories,
        ]);
    }



    public function update(Request $request, $id)
    {
        $product = WarehouseProduct::findOrFail($id);

        $request->validate([
            'qty' => 'required|numeric|min:0',
            'price' => 'required|numeric|min:0',
        ]);

        $product->update([
            'qty' => $request->qty,
            'price' => $request->price,
        ]);

        return response()->json(['message' => 'Product updated successfully.']);
    }

    public function export(Request $request)
    {
        $query = WarehouseProduct::with([
            'supplierProductDetail.product.category',
            'supplierProductDetail.variation',
            'supplierProductDetail.supplier',
            'warehouse'
        ]);

        if ($request->filled('warehouse')) {
            $query->where('warehouse_id', $request->warehouse);
        }

        if ($request->filled('supplier')) {
            $query->whereHas('supplierProductDetail.supplier', function ($q) use ($request) {
                $q->where('id', $request->supplier);
            });
        }

        if ($request->filled('product')) {
            $query->whereHas('supplierProductDetail.product', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->product . '%');
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        $products = $query->get();

        $csv = [];
        $csv[] = [
            'Warehouse',
            'Product',
            'Category',
            'Variation',
            'Supplier',
            'Quantity',
            'Price',
            'Supplier Price'
        ];

        foreach ($products as $product) {
            $csv[] = [
                $product->warehouse->name ?? '',
                $product->supplierProductDetail->product->name ?? '',
                $product->supplierProductDetail->product->category->name ?? '',
                $product->supplierProductDetail->variation->name ?? '',
                $product->supplierProductDetail->supplier->name ?? '',
                $product->qty,
                $product->price,
                $product->supplierProductDetail->price ?? '',
            ];
        }

        $filename = 'inventory_export_' . now()->format('Ymd_His') . '.csv';

        $handle = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return Response::make($content, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'product_name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'qty' => 'required|integer|min:0',
            'critical_level_qty' => 'nullable|integer|min:0',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // Duplicate Check
            $exists = Product::where('name', $validated['product_name'])
                ->where('category_id', $validated['category_id'])
                ->first();

            if ($exists) {
                return response()->json([
                    'message' => 'Product already exists in this category.'
                ], 422);
            }

            // Create Product
            $product = Product::create([
                'name' => $validated['product_name'],
                'category_id' => $validated['category_id'],
                'company_id' => auth()->user()->company_id ?? 1,
            ]);

            // Create default product variation if none exists
            $defaultVariation = ProductVariation::firstOrCreate(
                ['is_default' => true],
                ['name' => 'Default Product Variation']
            );

            // Insert Supplier Product record
            SupplierProduct::updateOrCreate(
                [
                    'supplier_id' => $validated['supplier_id'],
                    'product_id' => $product->id,
                ],
                [
                    'has_variation' => 1,
                ]
            );

            // Insert into supplier_product_details
            $supplierProduct = SupplierProductDetail::create([
                'supplier_id' => $validated['supplier_id'],
                'product_id' => $product->id,
                'product_variation_id' => $defaultVariation->id,
                'price' => $validated['cost_price'],
                'cost' => $validated['cost_price'],
                'currency' => 'PHP',
                'barcode' => strtoupper(Str::random(10)),
            ]);

            // Create Warehouse Product
            $warehouseProduct = WarehouseProduct::create([
                'warehouse_id' => $validated['warehouse_id'],
                'product_id' => $product->id,
                'supplier_product_detail_id' => $supplierProduct->id,
                'sku' => 'SKU-' . strtoupper(Str::random(5)),
                'barcode' => strtoupper(Str::random(10)),
                'qty' => $validated['qty'],
                'critical_level_qty' => $validated['critical_level_qty'],
                'price' => $validated['selling_price'],
                'last_cost' => $validated['cost_price'],
            ]);

            // Log initial stock adjustment
            WarehouseStockAdjustment::create([
                'warehouse_id' => $validated['warehouse_id'],
                'warehouse_product_id' => $warehouseProduct->id,
                'system_quantity' => 0,
                'actual_quantity' => $validated['qty'],
                'adjustment' => $validated['qty'],
                'reason' => 'count-correction',
                'remarks' => 'Initial stock on creation',
                'adjusted_at' => now(),
                'adjusted_by_user_id' => auth()->id(),
            ]);

            DB::commit();

            return response()->json(['message' => 'Product added successfully.'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create product.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // public function bulkUpload(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:csv,txt',
    //     ]);

    //     $file = $request->file('file');
    //     $data = array_map('str_getcsv', file($file));
    //     $headers = array_map('trim', array_shift($data)); // First row as headers

    //     $errors = [];
    //     DB::beginTransaction();

    //     try {
    //         foreach ($data as $index => $row) {
    //             $row = array_combine($headers, $row);

    //             $validator = Validator::make($row, [
    //                 'product_name' => 'required|string',
    //                 'category_id' => 'required|exists:categories,id',
    //                 'supplier_id' => 'required|exists:suppliers,id',
    //                 'warehouse_id' => 'required|exists:warehouses,id',
    //                 'qty' => 'required|integer|min:0',
    //                 'critical_level_qty' => 'nullable|integer|min:0',
    //                 'cost_price' => 'required|numeric|min:0',
    //                 'selling_price' => 'required|numeric|min:0',
    //             ]);

    //             if ($validator->fails()) {
    //                 $errors[] = [
    //                     'row' => $index + 2, // +2 for header and 0-index
    //                     'errors' => $validator->errors()->all(),
    //                 ];
    //                 continue;
    //             }

    //             // Check for existing product
    //             $existing = Product::where('name', $row['product_name'])
    //                 ->where('category_id', $row['category_id'])
    //                 ->first();

    //             if ($existing) {
    //                 $errors[] = [
    //                     'row' => $index + 2,
    //                     'errors' => ['Product already exists.'],
    //                 ];
    //                 continue;
    //             }

    //             // Create product
    //             $product = Product::create([
    //                 'name' => $row['product_name'],
    //                 'category_id' => $row['category_id'],
    //                 'company_id' => auth()->user()->company_id ?? 1,
    //             ]);

    //             $variation = ProductVariation::firstOrCreate(
    //                 ['is_default' => true],
    //                 ['name' => 'Default Product Variation']
    //             );

    //             SupplierProduct::updateOrCreate(
    //                 [
    //                     'supplier_id' => $row['supplier_id'],
    //                     'product_id' => $product->id,
    //                 ],
    //                 ['has_variation' => 1]
    //             );

    //             $supplierProduct = SupplierProductDetail::create([
    //                 'supplier_id' => $row['supplier_id'],
    //                 'product_id' => $product->id,
    //                 'product_variation_id' => $variation->id,
    //                 'price' => $row['cost_price'],
    //                 'cost' => $row['cost_price'],
    //                 'currency' => 'PHP',
    //                 'barcode' => strtoupper(Str::random(10)),
    //             ]);

    //             $warehouseProduct = WarehouseProduct::create([
    //                 'warehouse_id' => $row['warehouse_id'],
    //                 'product_id' => $product->id,
    //                 'supplier_product_detail_id' => $supplierProduct->id,
    //                 'sku' => 'SKU-' . strtoupper(Str::random(5)),
    //                 'barcode' => strtoupper(Str::random(10)),
    //                 'qty' => $row['qty'],
    //                 'critical_level_qty' => $row['critical_level_qty'],
    //                 'price' => $row['selling_price'],
    //                 'last_cost' => $row['cost_price'],
    //             ]);

    //             WarehouseStockAdjustment::create([
    //                 'warehouse_id' => $row['warehouse_id'],
    //                 'warehouse_product_id' => $warehouseProduct->id,
    //                 'system_quantity' => 0,
    //                 'actual_quantity' => $row['qty'],
    //                 'adjustment' => $row['qty'],
    //                 'reason' => 'count-correction',
    //                 'remarks' => 'Bulk upload initial stock',
    //                 'adjusted_at' => now(),
    //                 'adjusted_by_user_id' => auth()->id(),
    //             ]);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Bulk upload completed.',
    //             'errors' => $errors,
    //         ]);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Bulk upload failed.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');
        $data = array_map('str_getcsv', file($file));
        $headers = array_map('trim', array_shift($data)); // First row as headers

        $errors = [];
        DB::beginTransaction();

        try {
            foreach ($data as $index => $row) {
                $row = array_combine($headers, $row);

                // Resolve names to IDs
                $warehouse = \App\Models\Warehouse::where('name', trim($row['Warehouse']))->first();
                $supplier = \App\Models\Supplier::where('name', trim($row['Supplier']))->first();
                $category = \App\Models\Category::where('name', trim($row['Category']))->first();

                $validatedRow = [
                    'product_name' => trim($row['Product Name'] ?? ''),
                    'category_id' => $category->id ?? null,
                    'supplier_id' => $supplier->id ?? null,
                    'warehouse_id' => $warehouse->id ?? null,
                    'qty' => (int) $row['Qty'] ?? null,
                    'critical_level_qty' => (int) $row['Critical Level'] ?? 0,
                    'cost_price' => (float) $row['Cost Price'] ?? null,
                    'selling_price' => (float) $row['Selling Price'] ?? null,
                ];

                $validator = Validator::make($validatedRow, [
                    'product_name' => 'required|string',
                    'category_id' => 'required|exists:categories,id',
                    'supplier_id' => 'required|exists:suppliers,id',
                    'warehouse_id' => 'required|exists:warehouses,id',
                    'qty' => 'required|integer|min:0',
                    'critical_level_qty' => 'nullable|integer|min:0',
                    'cost_price' => 'required|numeric|min:0',
                    'selling_price' => 'required|numeric|min:0',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $index + 2,
                        'errors' => $validator->errors()->all(),
                    ];
                    continue;
                }

                // Check for existing product
                $existing = \App\Models\Product::where('name', $validatedRow['product_name'])
                    ->where('category_id', $validatedRow['category_id'])
                    ->first();

                if ($existing) {
                    $errors[] = [
                        'row' => $index + 2,
                        'errors' => ['Product already exists.'],
                    ];
                    continue;
                }

                // Create product
                $product = \App\Models\Product::create([
                    'name' => $validatedRow['product_name'],
                    'category_id' => $validatedRow['category_id'],
                    'company_id' => auth()->user()->company_id ?? 1,
                ]);

                $variation = \App\Models\ProductVariation::firstOrCreate(
                    [
                        'product_id' => $product->id,
                        'is_default' => true,
                    ],
                    [
                        'name' => 'Default Product Variation',
                        'sku' => 'SKU-' . strtoupper(Str::random(5)),
                        'barcode' => strtoupper(Str::random(10)),
                        'price' => $validatedRow['selling_price'],
                    ]
                );


                \App\Models\SupplierProduct::updateOrCreate(
                    [
                        'supplier_id' => $validatedRow['supplier_id'],
                        'product_id' => $product->id,
                    ],
                    ['has_variation' => 1]
                );

                $supplierProduct = \App\Models\SupplierProductDetail::create([
                    'supplier_id' => $validatedRow['supplier_id'],
                    'product_id' => $product->id,
                    'product_variation_id' => $variation->id,
                    'price' => $validatedRow['cost_price'],
                    'cost' => $validatedRow['cost_price'],
                    'currency' => 'PHP',
                    'barcode' => strtoupper(Str::random(10)),
                ]);

                $warehouseProduct = \App\Models\WarehouseProduct::create([
                    'warehouse_id' => $validatedRow['warehouse_id'],
                    'product_id' => $product->id,
                    'supplier_product_detail_id' => $supplierProduct->id,
                    'sku' => 'SKU-' . strtoupper(Str::random(5)),
                    'barcode' => strtoupper(Str::random(10)),
                    'qty' => $validatedRow['qty'],
                    'critical_level_qty' => $validatedRow['critical_level_qty'],
                    'price' => $validatedRow['selling_price'],
                    'last_cost' => $validatedRow['cost_price'],
                ]);

                WarehouseStockAdjustment::create([
                    'warehouse_id' => $validatedRow['warehouse_id'],
                    'warehouse_product_id' => $warehouseProduct->id,
                    'system_quantity' => 0,
                    'actual_quantity' => $validatedRow['qty'],
                    'adjustment' => $validatedRow['qty'],
                    'reason' => 'count-correction',
                    'remarks' => 'Bulk upload initial stock',
                    'adjusted_at' => now(),
                    'adjusted_by_user_id' => auth()->id(),
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Bulk upload completed.',
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Bulk upload failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
