<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $accountMap = [
            'Electricity' => 'Utilities Expense',
            'Water' => 'Utilities Expense',
            'Internet' => 'Utilities Expense',
            'Telephone' => 'Utilities Expense',

            'Office Rent' => 'Rent Expense',
            'Warehouse Rent' => 'Rent Expense',
            'Equipment Lease' => 'Rent Expense',

            'Employee Salaries' => 'Salaries Expense',
            'Overtime Pay' => 'Salaries Expense',
            'Bonuses' => 'Salaries Expense',

            'Fuel' => 'Transportation Expense',
            'Vehicle Maintenance' => 'Transportation Expense',
            'Delivery Expenses' => 'Transportation Expense',

            'Building Repairs' => 'Repairs and Maintenance Expense',
            'Equipment Repairs' => 'Repairs and Maintenance Expense',
            'IT Maintenance' => 'Repairs and Maintenance Expense',

            'Office Supplies' => 'Office Expense',
            'Printing and Stationery' => 'Office Expense',
            'Postage and Courier' => 'Office Expense',

            'Legal Fees' => 'Professional Fees',
            'Accounting Services' => 'Professional Fees',
            'Consulting Fees' => 'Professional Fees',

            'Business Permit' => 'Taxes and Licenses',
            'VAT Payments' => 'Taxes and Licenses',
            'Other Government Fees' => 'Taxes and Licenses',

            'Property Insurance' => 'Insurance Expense',
            'Vehicle Insurance' => 'Insurance Expense',
            'Health Insurance' => 'Insurance Expense',

            'Online Advertising' => 'Advertising and Marketing',
            'Print Advertising' => 'Advertising and Marketing',
            'Promotional Events' => 'Advertising and Marketing',

            'Other Expenses' => 'Miscellaneous Expense',
        ];

        $categories = [
            'Utilities' => ['Electricity', 'Water', 'Internet', 'Telephone'],
            'Rentals' => ['Office Rent', 'Warehouse Rent', 'Equipment Lease'],
            'Salaries and Wages' => ['Employee Salaries', 'Overtime Pay', 'Bonuses'],
            'Transportation' => ['Fuel', 'Vehicle Maintenance', 'Delivery Expenses'],
            'Repairs and Maintenance' => ['Building Repairs', 'Equipment Repairs', 'IT Maintenance'],
            'Office Expenses' => ['Office Supplies', 'Printing and Stationery', 'Postage and Courier'],
            'Professional Fees' => ['Legal Fees', 'Accounting Services', 'Consulting Fees'],
            'Taxes and Licenses' => ['Business Permit', 'VAT Payments', 'Other Government Fees'],
            'Insurance' => ['Property Insurance', 'Vehicle Insurance', 'Health Insurance'],
            'Advertising and Marketing' => ['Online Advertising', 'Print Advertising', 'Promotional Events'],
            'Miscellaneous' => ['Other Expenses'],
        ];

        foreach ($categories as $parent => $children) {
            // Try to get default account from first child
            $firstAccountName = $accountMap[$children[0]] ?? null;
            $parentAccountId = $firstAccountName
                ? DB::table('accounts')->whereRaw('LOWER(name) = ?', [strtolower($firstAccountName)])->value('id')
                : null;

            $parentId = DB::table('categories')->insertGetId([
                'related_model' => 'expenses',
                'parent_id' => null,
                'name' => $parent,
                'default_account_id' => $parentAccountId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($children as $child) {
                $accountName = $accountMap[$child] ?? null;

                $accountId = $accountName
                    ? DB::table('accounts')->whereRaw('LOWER(name) = ?', [strtolower($accountName)])->value('id')
                    : null;

                if (!$accountId) {
                    Log::warning("⚠️ No account found for child category '$child' (mapped to '$accountName')");
                }

                DB::table('categories')->insert([
                    'related_model' => 'expenses',
                    'parent_id' => $parentId,
                    'name' => $child,
                    'default_account_id' => $accountId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
