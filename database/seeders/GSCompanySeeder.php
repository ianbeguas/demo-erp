<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\User;
use App\Models\ApprovalLevelSetting;
use Illuminate\Support\Str;

class GSCompanySeeder extends Seeder
{
    public function run()
    {
        // Create Great Swiss Company
        $company = Company::create([
            'name' => 'Great Swiss',
            'email' => 'info@greatswiss.com',
            'address' => '123 Swiss Innovation Park, Zurich, Switzerland',
            'description' => "Great Swiss is a global leader in premium home and industrial solutions, combining Swiss precision with modern technology to deliver unparalleled quality and design.",
            'mobile' => '41791234567',
            'website' => 'https://www.greatswiss.com/',
            'created_by_user_id' => null,  // Temporarily null, will update after user creation
            'country_id' => 221,  // Switzerland (adjust if different in your countries table)
            'avatar' => 'app-settings/app-logo.png',  // Retained the same logo path
            'created_at' => now(),
            'updated_at' => now(),
            'token' => Str::random(64),
        ]);

        // Create Super Admin User for Great Swiss
        $superAdmin = User::factory()->withPersonalTeam()->create([
            'name' => 'Great Swiss Super Admin',
            'email' => 'super.admin@greatswiss.com',
            'password' => bcrypt('123123123'),
            'company_id' => $company->id,
        ]);

        // Create Admin User for Great Swiss
        $adminUser = User::factory()->withPersonalTeam()->create([
            'name' => 'Great Swiss Admin',
            'email' => 'admin@greatswiss.com',
            'password' => bcrypt('123123123'),
            'company_id' => $company->id,
        ]);

        // Assign roles
        $superAdmin->assignRole('super-admin');
        $adminUser->assignRole('admin');

        // Update created_by_user_id in Company
        $company->update(['created_by_user_id' => $superAdmin->id]);

        // Create Approval Level Settings
        ApprovalLevelSetting::create([
            'type' => 'purchase-order',
            'company_id' => $company->id,
            'user_id' => $superAdmin->id,
            'level' => 2,
            'label' => 'Checked By:',
        ]);

        ApprovalLevelSetting::create([
            'type' => 'purchase-order',
            'company_id' => $company->id,
            'user_id' => $adminUser->id,
            'level' => 1,
            'label' => 'Approved By:',
        ]);
    }
}
