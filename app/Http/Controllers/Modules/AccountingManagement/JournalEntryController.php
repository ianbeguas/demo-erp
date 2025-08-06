<?php

namespace App\Http\Controllers\Modules\AccountingManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class JournalEntryController extends Controller
{
    protected $modelClass;
    protected $modelName;
    protected $modulePath;

    public function __construct()
    {
        $this->modelClass = \App\Models\JournalEntry::class;
        $this->modelName = Str::plural(Str::singular(class_basename($this->modelClass)));
        $this->modulePath = 'Modules/AccountingManagement';
    }

    public function index()
    {
        return Inertia::render("{$this->modulePath}/{$this->modelName}/Index");
    }

    public function export()
    {
        return Inertia::render("{$this->modulePath}/{$this->modelName}/Export");
    }

    public function create()
    {
        $categoryQuery = \App\Models\Category::where('related_model', 'expenses')->where('default_account_id', '!=', null);
        $categories = $categoryQuery->orderBy('name', 'asc')->get();
        $suppliersQuery = \App\Models\Supplier::orderBy('name', 'asc');
        $suppliers = $suppliersQuery->get();
        $companiesQuery = \App\Models\Company::orderBy('name', 'asc');
        $companies = $companiesQuery->get();

        return Inertia::render("{$this->modulePath}/{$this->modelName}/Create", [
            'categories' => $categories,
            'suppliers' => $suppliers,
            'companies' => $companies,
        ]);
    }

    public function show($id)
    {
        $model = $this->modelClass::with(['company', 'details', 'details.account'])->findOrFail($id);

        return Inertia::render("{$this->modulePath}/{$this->modelName}/Show", [
            'modelData' => $model,
        ]);
    }

    public function edit($id)
    {
        $model = $this->modelClass::with(['company'])->findOrFail($id);
        $query = \App\Models\Category::where('related_model', 'expenses');
        $categories = $query->orderBy('name', 'asc')->get();
        $suppliersQuery = \App\Models\Supplier::orderBy('name', 'asc');
        $suppliers = $suppliersQuery->get();
        $companiesQuery = \App\Models\Company::orderBy('name', 'asc');
        $companies = $companiesQuery->get();

        return Inertia::render("{$this->modulePath}/{$this->modelName}/Edit", [
            'modelData' => $model,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'companies' => $companies,
        ]);
    }
}
