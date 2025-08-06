<?php

namespace App\Http\Controllers\Modules\WarehouseManagement;

use App\Http\Controllers\Controller;
use App\Models\WarehouseStockTransfer;
use App\Settings\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class WarehouseStockTransferController extends Controller
{
    protected $modelClass;
    protected $modelName;
    protected $modulePath;

    public function __construct()
    {
        $this->modelClass = \App\Models\WarehouseStockTransfer::class;
        $this->modelName = Str::plural(Str::singular(class_basename($this->modelClass)));
        $this->modulePath = 'Modules/WarehouseManagement';
    }
public function scanPage(WarehouseStockTransfer $transfer)
{
    $transfer->load([
        'destinationWarehouse',
        'details.originProduct.serials',
        'details.destinationProduct',
    ]);

    foreach ($transfer->details as $detail) {
        if ($detail->originProduct && $detail->originProduct->has_serials) {
            $serials = $detail->originProduct->serials->take($detail->expected_qty);
            $detail->originProduct->setRelation('serials', $serials);
        }
    }

    return Inertia::render('Modules/WarehouseManagement/WarehouseOperations/StockTransfer/Scan', [
        'transfer' => $transfer,
        'savedProgress' => $transfer->scan_progress ?? ['scanned_serials' => [], 'manually_completed_details' => []],
    ]);
}
public function createV2Page()
{
    return Inertia::render('Modules/WarehouseManagement/WarehouseOperations/StockTransfer/CreateV2');
}



    public function index()
    {
        return Inertia::render("{$this->modulePath}/{$this->modelName}/Index");
    }

    public function create()
    {
        return Inertia::render("{$this->modulePath}/{$this->modelName}/Create");
    }

    public function show($id)
    {
        $model = $this->modelClass::with([
            'originWarehouse',
            'originWarehouse.company',
            'destinationWarehouse',
            'destinationWarehouse.company',
            'details.originWarehouseProduct',
            'details.originWarehouseProduct.supplierProductDetail',
            'details.originWarehouseProduct.supplierProductDetail.productVariation',
            'details.destinationWarehouseProduct',
            'details.destinationWarehouseProduct.supplierProductDetail',
            'details.destinationWarehouseProduct.supplierProductDetail.productVariation',
            'createdByUser',
            'details',
            'details.serials'
        ])->findOrFail($id);

        return Inertia::render("{$this->modulePath}/{$this->modelName}/Show", [
            'modelData' => $model,
            'receive_with_serial' => app(AppSettings::class)->receive_with_serial,
        ]);
    }

    public function edit($id)
    {
        $model = $this->modelClass::findOrFail($id);

        return Inertia::render("{$this->modulePath}/{$this->modelName}/Edit", [
            'modelData' => $model,
        ]);
    }
}