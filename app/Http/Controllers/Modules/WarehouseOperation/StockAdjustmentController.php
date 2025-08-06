<?php

namespace App\Http\Controllers\Modules\WarehouseOperation;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        return Inertia::render('Modules/WarehouseManagement/WarehouseOperations/StockAdjustment/Index');
    }
}
