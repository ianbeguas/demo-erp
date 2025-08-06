<?php

namespace App\Http\Controllers\Modules\WarehouseManagement;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class InventoryController extends Controller
{
    public function index()
{
    return Inertia::render('Modules/WarehouseManagement/Inventory/Index');
}
public function import()
{
    return Inertia::render('Modules/WarehouseManagement/Inventory/Import');
}




}