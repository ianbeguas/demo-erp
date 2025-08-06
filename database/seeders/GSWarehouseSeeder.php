<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Company;

class GSWarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Get the Great Swiss company ID
        $company = Company::where('name', 'Great Swiss')->first();

        if (!$company) {
            $this->command->error('Company "Great Swiss" not found. Please run GSCompanySeeder first.');
            return;
        }

        $locations = [
            [
                'name' => 'Great Swiss Main Warehouse',
                'address' => 'Swiss Innovation Park, Zurich, Switzerland',
                'mobile' => '41791234567',
            ],
            [
                'name' => 'Great Swiss Distribution Center',
                'address' => 'Bern Logistic Hub, Bern, Switzerland',
                'mobile' => '41791112233',
            ],
            [
                'name' => 'Great Swiss Showroom',
                'address' => 'Lakeside Business Plaza, Lucerne, Switzerland',
                'mobile' => '41792223344',
            ],
        ];

        foreach ($locations as $location) {
            DB::table('warehouses')->insert([
                'company_id' => $company->id,
                'token' => Str::random(64),
                'slug' => Str::slug($location['name']),
                'created_by_user_id' => $company->created_by_user_id,
                'category_id' => null,
                'country_id' => 221, // Switzerland (Adjust as per your countries table)
                'name' => $location['name'],
                'email' => null,
                'landline' => null,
                'mobile' => $location['mobile'] ?? null,
                'address' => $location['address'],
                'description' => "Facility located at {$location['address']}",
                'website' => null,
                'avatar' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command->info('Great Swiss Warehouses seeded successfully.');
    }
}
