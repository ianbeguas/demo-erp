<?php

namespace App\Http\Controllers\Modules\WarehouseManagement;

use App\Http\Controllers\Controller;
use App\Models\WarehouseStockTransfer;
use App\Models\WarehouseStockTransferSerial;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WarehouseOperationController extends Controller
{
    public function index()
{
    // Fetch latest stock transfers with status pending or approved
    $transfers = WarehouseStockTransfer::with(['originWarehouse', 'destinationWarehouse'])
        ->whereIn('status', ['pending', 'approved'])
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    return Inertia::render('Modules/WarehouseManagement/WarehouseOperations/Index', [
        'pendingTransfers' => $transfers,
    ]);
}
    public function createStockTransfer()
    {
        return inertia('Modules/WarehouseManagement/WarehouseOperations/StockTransfer/Create');
    }
  public function viewTransfer($id)
{
    $transfers = \App\Models\WarehouseStockTransfer::with(['originWarehouse', 'destinationWarehouse'])
        ->whereIn('status', ['pending', 'approved'])
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();

    return Inertia::render('Modules/WarehouseManagement/WarehouseOperations/Index', [
        'pendingTransfers' => $transfers,
        'selectedTransferId' => $id, // Pass the selected transfer ID
    ]);
}

// public function getStockTransferDetails($id)
// {
//     $transfer = WarehouseStockTransfer::with([
//         'originWarehouse',
//         'destinationWarehouse',
//         'details.originProduct.supplierProductDetail.product',
//         'details.originProduct.serials'
//     ])->findOrFail($id);

//     return response()->json(['data' => $transfer]);
// }
public function getStockTransferDetails($id)
{
    $transfer = WarehouseStockTransfer::with([
        'originWarehouse',
        'destinationWarehouse',
        'details.originProduct.supplierProductDetail.product',
        'details.originProduct.serials'
    ])->findOrFail($id);

    foreach ($transfer->details as $detail) {
        $matchedSerials = WarehouseStockTransferSerial::where('warehouse_stock_transfer_detail_id', $detail->id)
            ->pluck('serial_number');

        $detail->matched_serials = $matchedSerials;
    }

    return response()->json(['data' => $transfer]);
}

    public function updateTransferStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:approved,rejected,cancelled'
    ]);

    $transfer = WarehouseStockTransfer::findOrFail($id);
    $transfer->status = $request->status;
    $transfer->save();

    return response()->json(['message' => 'Status updated successfully.']);
}


}
