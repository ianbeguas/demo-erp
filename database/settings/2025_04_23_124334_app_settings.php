<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;
use Illuminate\Support\Facades\DB;

return new class extends SettingsMigration {
    public function up(): void
    {
        $now = now();

        /*
        $settings = [
            // General Info
            ['name' => 'name', 'payload' => 'Oh Ed'],
            ['name' => 'description', 'payload' => 'Oh Ed is an IT firm...'],
            ['name' => 'icon', 'payload' => '/app-settings/app-icon.avif'],
            ['name' => 'logo', 'payload' => '/app-settings/app-logo.avif'],
            ['name' => 'website', 'payload' => 'https://edeesonopina.vercel.app'],

            // Navbar
            ['name' => 'navbar_bg_color', 'payload' => '#fff71a'], // Not active
            ['name' => 'navbar_text_color', 'payload' => '#212121'], // Not active
            ['name' => 'navbar_hover_bg_color', 'payload' => '#fff71a'], // Not active
            ['name' => 'navbar_hover_text_color', 'payload' => '#212121'], // Not active
            ['name' => 'navbar_active_bg_color', 'payload' => '#fff71a'],
            ['name' => 'navbar_active_text_color', 'payload' => '#212121'],

            // Sidebar
            ['name' => 'sidebar_bg_color', 'payload' => '#3355db'], // Not active
            ['name' => 'sidebar_text_color', 'payload' => '#212121'], // Not active
            ['name' => 'sidebar_hover_bg_color', 'payload' => '#3355db'], // Not active
            ['name' => 'sidebar_hover_text_color', 'payload' => '#212121'], // Not active
            ['name' => 'sidebar_active_bg_color', 'payload' => '#3355db'],
            ['name' => 'sidebar_active_text_color', 'payload' => '#ffffff'],

            // Button
            ['name' => 'button_primary_bg_color', 'payload' => '#3355db'],
            ['name' => 'button_primary_text_color', 'payload' => '#ffffff'],

            // Input
            ['name' => 'input_active_bg_color', 'payload' => '#3355db'],

            // Theme Colors
            ['name' => 'primary_color', 'payload' => '#fff71a'],
            ['name' => 'secondary_color', 'payload' => '#6B7280'],
            ['name' => 'success_color', 'payload' => '#10B981'],
            ['name' => 'danger_color', 'payload' => '#EF4444'],
            ['name' => 'warning_color', 'payload' => '#F59E0B'],
            ['name' => 'info_color', 'payload' => '#0EA5E9'],

            // PUSHER
            ['name' => 'pusher_app_id', 'payload' => '1916347'],
            ['name' => 'pusher_app_key', 'payload' => '41ca495b13c5e75c1f91'],
            ['name' => 'pusher_app_secret', 'payload' => '6242f3214cf1f830b548'],
            ['name' => 'pusher_app_cluster', 'payload' => 'ap1'],

            // GOOGLE
            ['name' => 'google_client_id', 'payload' => ''],
            ['name' => 'google_client_secret', 'payload' => ''],
            ['name' => 'google_redirect_uri', 'payload' => 'http://127.0.0.1:8000/auth/google/callback'],

            // STRIPE
            ['name' => 'stripe_key', 'payload' => 'pk_test_...'],
            ['name' => 'stripe_secret', 'payload' => 'sk_test_...'],
            ['name' => 'stripe_webhook_secret', 'payload' => ''],

            // OPENAI
            ['name' => 'openai_api_url', 'payload' => 'https://api.openai.com/v1'],
            ['name' => 'openai_api_key', 'payload' => 'sk-proj-...'],

            // CLAUDE (add your own keys)
            ['name' => 'claude_api_url', 'payload' => null],
            ['name' => 'claude_api_key', 'payload' => null],
        ];
        */
        // KANZEN
        // $settings = [
        //     // General Info
        //     ['name' => 'name', 'payload' => 'Kanzen'],
        //     ['name' => 'description', 'payload' => 'Kanzen is a software development company...'],
        //     ['name' => 'icon', 'payload' => '/app-settings/app-icon.png'],
        //     ['name' => 'logo', 'payload' => '/app-settings/app-logo.png'],
        //     ['name' => 'website', 'payload' => 'https://edeesonopina.vercel.app'],

        //     // Navbar
        //     ['name' => 'navbar_bg_color', 'payload' => '#1C1C1C'], // Not active
        //     ['name' => 'navbar_text_color', 'payload' => '#ffffff'], // Not active
        //     ['name' => 'navbar_hover_bg_color', 'payload' => '#ffffff'], // Not active
        //     ['name' => 'navbar_hover_text_color', 'payload' => '#212121'], // Not active
        //     ['name' => 'navbar_active_bg_color', 'payload' => '#1C1C1C'],
        //     ['name' => 'navbar_active_text_color', 'payload' => '#212121'],

        //     // Sidebar
        //     ['name' => 'sidebar_bg_color', 'payload' => '#1C1C1C'], // Not active
        //     ['name' => 'sidebar_text_color', 'payload' => '#212121'], // Not active
        //     ['name' => 'sidebar_hover_bg_color', 'payload' => '#1C1C1C'], // Not active
        //     ['name' => 'sidebar_hover_text_color', 'payload' => '#212121'], // Not active
        //     ['name' => 'sidebar_active_bg_color', 'payload' => '#1C1C1C'],
        //     ['name' => 'sidebar_active_text_color', 'payload' => '#ffffff'],

        //     // Button
        //     ['name' => 'button_primary_bg_color', 'payload' => '#1C1C1C'],
        //     ['name' => 'button_primary_text_color', 'payload' => '#ffffff'],

        //     // Input
        //     ['name' => 'input_active_bg_color', 'payload' => '#1C1C1C'],

        //     // Theme Colors
        //     ['name' => 'primary_color', 'payload' => '#1C1C1C'],
        //     ['name' => 'secondary_color', 'payload' => '#6B7280'],
        //     ['name' => 'success_color', 'payload' => '#10B981'],
        //     ['name' => 'danger_color', 'payload' => '#EF4444'],
        //     ['name' => 'warning_color', 'payload' => '#F59E0B'],
        //     ['name' => 'info_color', 'payload' => '#0EA5E9'],

        //     // PUSHER
        //     ['name' => 'pusher_app_id', 'payload' => '1916347'],
        //     ['name' => 'pusher_app_key', 'payload' => '41ca495b13c5e75c1f91'],
        //     ['name' => 'pusher_app_secret', 'payload' => '6242f3214cf1f830b548'],
        //     ['name' => 'pusher_app_cluster', 'payload' => 'ap1'],

        //     // GOOGLE
        //     ['name' => 'google_client_id', 'payload' => ''],
        //     ['name' => 'google_client_secret', 'payload' => ''],
        //     ['name' => 'google_redirect_uri', 'payload' => 'http://127.0.0.1:8000/auth/google/callback'],

        //     // STRIPE
        //     ['name' => 'stripe_key', 'payload' => 'pk_test_...'],
        //     ['name' => 'stripe_secret', 'payload' => 'sk_test_...'],
        //     ['name' => 'stripe_webhook_secret', 'payload' => ''],

        //     // OPENAI
        //     ['name' => 'openai_api_url', 'payload' => 'https://api.openai.com/v1'],
        //     ['name' => 'openai_api_key', 'payload' => 'sk-proj-...'],

        //     // CLAUDE (add your own keys)
        //     ['name' => 'claude_api_url', 'payload' => null],
        //     ['name' => 'claude_api_key', 'payload' => null],
        // ];
        // Great Swiss
        // $settings = [
        //     // General Info
        //     ['name' => 'name', 'payload' => 'Great Swiss'],
        //     ['name' => 'description', 'payload' => 'Great Swiss Metalbuilders Corp is a premium provider of industrial and home solutions, crafted with Swiss precision and innovation.'],
        //     ['name' => 'icon', 'payload' => '/app-settings/app-icon.png'],
        //     ['name' => 'logo', 'payload' => '/app-settings/app-logo.png'],
        //     ['name' => 'website', 'payload' => 'https://greatswiss.com'],

        //     // Navbar Colors
        //     ['name' => 'navbar_bg_color', 'payload' => '#0D1B2A'], // Dark Navy Blue
        //     ['name' => 'navbar_text_color', 'payload' => '#FFFFFF'],
        //     ['name' => 'navbar_hover_bg_color', 'payload' => '#E63946'], // Red hover
        //     ['name' => 'navbar_hover_text_color', 'payload' => '#FFFFFF'],
        //     ['name' => 'navbar_active_bg_color', 'payload' => '#0D1B2A'],
        //     ['name' => 'navbar_active_text_color', 'payload' => '#FFFFFF'],

        //     // Sidebar Colors
        //     [
        //         'name' => 'sidebar_bg_color',
        //         'payload' => '#C0C0C0'  // Silver Background
        //     ],
        //     [
        //         'name' => 'sidebar_text_color',
        //         'payload' => '#0D1B2A' // Deep Navy Blue Text
        //     ],
        //     [
        //         'name' => 'sidebar_hover_bg_color',
        //         'payload' => '#E63946' // Red Hover Background
        //     ],
        //     [
        //         'name' => 'sidebar_hover_text_color',
        //         'payload' => '#FFFFFF' // White Hover Text
        //     ],
        //     [
        //         'name' => 'sidebar_active_bg_color',
        //         'payload' => '#0D1B2A' // Active Deep Blue Background
        //     ],
        //     [
        //         'name' => 'sidebar_active_text_color',
        //         'payload' => '#FFFFFF' // Active White Text
        //     ],

        //     // Button Colors
        //     ['name' => 'button_primary_bg_color', 'payload' => '#E63946'], // Red
        //     ['name' => 'button_primary_text_color', 'payload' => '#FFFFFF'],

        //     // Input Colors
        //     ['name' => 'input_active_bg_color', 'payload' => '#F1FAEE'], // Light Silver/White

        //     // Theme Colors
        //     ['name' => 'primary_color', 'payload' => '#0D1B2A'], // Deep Navy Blue
        //     ['name' => 'secondary_color', 'payload' => '#A8DADC'], // Soft Blue
        //     ['name' => 'success_color', 'payload' => '#06D6A0'],   // Vibrant Green (Optional)
        //     ['name' => 'danger_color', 'payload' => '#E63946'],    // Red
        //     ['name' => 'warning_color', 'payload' => '#FFD166'],   // Yellow
        //     ['name' => 'info_color', 'payload' => '#457B9D'],      // Blue-gray Silver tone

        //     // PUSHER
        //     ['name' => 'pusher_app_id', 'payload' => '1916347'],
        //     ['name' => 'pusher_app_key', 'payload' => '41ca495b13c5e75c1f91'],
        //     ['name' => 'pusher_app_secret', 'payload' => '6242f3214cf1f830b548'],
        //     ['name' => 'pusher_app_cluster', 'payload' => 'ap1'],

        //     // GOOGLE
        //     ['name' => 'google_client_id', 'payload' => ''],
        //     ['name' => 'google_client_secret', 'payload' => ''],
        //     ['name' => 'google_redirect_uri', 'payload' => 'http://127.0.0.1:8000/auth/google/callback'],

        //     // STRIPE
        //     ['name' => 'stripe_key', 'payload' => 'pk_test_...'],
        //     ['name' => 'stripe_secret', 'payload' => 'sk_test_...'],
        //     ['name' => 'stripe_webhook_secret', 'payload' => ''],

        //     // OPENAI
        //     ['name' => 'openai_api_url', 'payload' => 'https://api.openai.com/v1'],
        //     ['name' => 'openai_api_key', 'payload' => 'sk-proj-...'],

        //     // CLAUDE (optional keys)
        //     ['name' => 'claude_api_url', 'payload' => null],
        //     ['name' => 'claude_api_key', 'payload' => null],
        // ];
        $settings = [
    // General Info
    ['name' => 'name', 'payload' => 'Demo Company'],
    ['name' => 'description', 'payload' => 'Demo Company provides top-notch solutions for various industries with a focus on quality and innovation.'],
    ['name' => 'icon', 'payload' => '/app-settings/app-icon.png'],
    ['name' => 'logo', 'payload' => '/app-settings/app-logo.png'],
    ['name' => 'website', 'payload' => 'https://demo-company.com'],

    // Navbar Colors
    ['name' => 'navbar_bg_color', 'payload' => '#123456'], // Demo Dark Blue
    ['name' => 'navbar_text_color', 'payload' => '#FFFFFF'],
    ['name' => 'navbar_hover_bg_color', 'payload' => '#FF5733'], // Demo Red-Orange
    ['name' => 'navbar_hover_text_color', 'payload' => '#FFFFFF'],
    ['name' => 'navbar_active_bg_color', 'payload' => '#123456'],
    ['name' => 'navbar_active_text_color', 'payload' => '#FFFFFF'],

    // Sidebar Colors
    ['name' => 'sidebar_bg_color', 'payload' => '#EFEFEF'], // Light Gray
    ['name' => 'sidebar_text_color', 'payload' => '#333333'], // Dark Gray Text
    ['name' => 'sidebar_hover_bg_color', 'payload' => '#FF5733'], // Hover Orange
    ['name' => 'sidebar_hover_text_color', 'payload' => '#FFFFFF'], // Hover White Text
    ['name' => 'sidebar_active_bg_color', 'payload' => '#123456'], // Active Dark Blue
    ['name' => 'sidebar_active_text_color', 'payload' => '#FFFFFF'], // Active White Text

    // Button Colors
    ['name' => 'button_primary_bg_color', 'payload' => '#FF5733'], // Orange
    ['name' => 'button_primary_text_color', 'payload' => '#FFFFFF'],

    // Input Colors
    ['name' => 'input_active_bg_color', 'payload' => '#F9F9F9'], // Very Light Gray

    // Theme Colors
    ['name' => 'primary_color', 'payload' => '#123456'], // Dark Blue
    ['name' => 'secondary_color', 'payload' => '#89CFF0'], // Light Blue
    ['name' => 'success_color', 'payload' => '#28A745'],   // Green
    ['name' => 'danger_color', 'payload' => '#DC3545'],    // Red
    ['name' => 'warning_color', 'payload' => '#FFC107'],   // Yellow
    ['name' => 'info_color', 'payload' => '#17A2B8'],      // Cyan-ish

    // PUSHER (Demo values)
    ['name' => 'pusher_app_id', 'payload' => '123456'],
    ['name' => 'pusher_app_key', 'payload' => 'demo_pusher_key'],
    ['name' => 'pusher_app_secret', 'payload' => 'demo_pusher_secret'],
    ['name' => 'pusher_app_cluster', 'payload' => 'mt1'],

    // GOOGLE (Demo, empty client keys)
    ['name' => 'google_client_id', 'payload' => ''],
    ['name' => 'google_client_secret', 'payload' => ''],
    ['name' => 'google_redirect_uri', 'payload' => 'http://127.0.0.1:8000/auth/google/callback'],

    // STRIPE (Demo test keys)
    ['name' => 'stripe_key', 'payload' => 'pk_test_demoKey123'],
    ['name' => 'stripe_secret', 'payload' => 'sk_test_demoSecret123'],
    ['name' => 'stripe_webhook_secret', 'payload' => 'whsec_demoWebhook123'],

    // OPENAI (Demo key)
    ['name' => 'openai_api_url', 'payload' => 'https://api.openai.com/v1'],
    ['name' => 'openai_api_key', 'payload' => 'sk-proj-demoOpenAIKey'],

    // CLAUDE (Demo null keys)
    ['name' => 'claude_api_url', 'payload' => null],
    ['name' => 'claude_api_key', 'payload' => null],
];



        foreach ($settings as $setting) {
            DB::table('settings')->insert([
                'group' => 'app',
                'name' => $setting['name'],
                'payload' => json_encode($setting['payload']),
                'locked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
