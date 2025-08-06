<?php

namespace App\Http\Controllers\Api\Modules\CustomerRelationshipManagement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Warehouse;

class CustomerController extends Controller
{
    protected $modelClass;
    protected $modelName;

    public function __construct()
    {
        $this->modelClass = \App\Models\Customer::class;
        $this->modelName = class_basename($this->modelClass);
    }

    public function index()
    {
        return $this->modelClass::latest()->paginate(perPage: 10);
    }

    public function complete()
    {
        return $this->modelClass::latest()->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'landline' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1024',
            'website' => 'nullable|url|string|max:255',
            'avatar' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('companies/avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $validated['created_by_user_id'] = auth()->user()->id;
        $company = $this->modelClass::create($validated);

        return response()->json([
            'modelData' => $company,
            'message' => "{$this->modelName} '{$company->name}' created successfully.",
        ]);
    }

    public function show($id)
    {
        $model = $this->modelClass::findOrFail($id);
        return $model;
    }

    public function update(Request $request, $id)
    {
        $model = $this->modelClass::findOrFail($id);

        $validated = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|string|max:255',
            'mobile' => 'nullable|string|max:255',
            'landline' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1024',
            'website' => 'nullable|url|string|max:255',
            'avatar' => 'nullable|file|mimes:png,jpg,jpeg,svg|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($model->avatar && Storage::disk('public')->exists($model->avatar)) {
                Storage::disk('public')->delete($model->avatar);
            }

            $avatarPath = $request->file('avatar')->store('companies/avatars', 'public');
            $validated['avatar'] = $avatarPath;
        }

        $model->update($validated);

        return response()->json([
            'modelData' => $model,
            'message' => "{$this->modelName} '{$model->name}' updated successfully.",
        ]);
    }

    public function destroy($id)
    {
        $model = $this->modelClass::findOrFail($id);
        $model->delete();

        return response()->json(['message' => "{$this->modelName} deleted successfully."], 200);
    }

    public function restore($id)
    {
        $model = $this->modelClass::withTrashed()->findOrFail($id);
        $model->restore();

        return response()->json([
            'message' => "{$this->modelName} restored successfully."
        ], 200);
    }

    public function autocomplete(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:1',
            'warehouse_id' => 'nullable|exists:warehouses,id'
        ]);

        $query = $this->modelClass::query();

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('mobile', 'like', "%{$request->search}%");
            });
        }

        if ($request->warehouse_id) {
            $warehouse = Warehouse::findOrFail($request->warehouse_id);
            $query->where('company_id', $warehouse->company->id);
        }

        $modelData = $query->take(10)->get();

        return response()->json([
            'data' => $modelData,
            'message' => $modelData->isEmpty() 
                ? "No {$this->modelName}s found."
                : "{$this->modelName}s retrieved successfully."
        ], 200);
    }
}
