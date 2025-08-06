<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use App\Models\User;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Models\Company;
use App\Models\Warehouse;

class UserController extends Controller
{
    public function sendResetPasswordLink(Request $request, User $user)
    {
        try {
            Password::sendResetLink(['email' => $user->email]);

            return response()->json([
                'message' => 'Password reset email sent successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send the reset email.',
            ], 500);
        }
    }

    public function resendVerificationLink(Request $request, User $user)
    {
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'User email already verified.',
            ], 400);
        }

        try {
            $user->sendEmailVerificationNotification();

            return response()->json([
                'message' => 'Verification email sent successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send verification email.',
            ], 500);
        }
    }

    public function index()
    {
        return Inertia::render('Users/Index');
    }

    public function create()
    {
        $roles = Role::all();
        $companiesQuery = Company::query();
        $companies = $companiesQuery->orderBy('name', 'asc')->get();
        //  $warehouses = Warehouse::select('id', 'name')->orderBy('name')->get();
        $warehouses = Warehouse::select('id', 'name', 'company_id')->orderBy('name')->get();

        return Inertia::render('Users/Create', [
            'roles' => $roles,
            'companies' => $companies,
            'warehouses' => $warehouses,
        ]);
    }

    public function show(User $user)
    {
        $user->role = $user->getRoleNames()->first();
        return Inertia::render('Users/Show', [
            'modelData' => $user,
        ]);
    }

    public function edit(User $user)
    {
        $user->role = $user->getRoleNames()->first();
        $roles = Role::all();
        $companiesQuery = Company::query();
        $user->warehouse_ids = $user->warehouses()->pluck('warehouses.id');
        $companies = $companiesQuery->orderBy('name', 'asc')->get();
        $warehouses = Warehouse::select('id', 'name', 'company_id')->orderBy('name')->get();
        return Inertia::render('Users/Edit', [
            'modelData' => $user,
            'roles' => $roles,
            'companies' => $companies,
            'warehouses' => $warehouses,
        ]);
    }

    public function changePassword(User $user)
    {
        $user->role = $user->getRoleNames()->first();
        $roles = Role::all();
        return Inertia::render('Users/ChangePassword', [
            'modelData' => $user,
            'roles' => $roles,
        ]);
    }
}
