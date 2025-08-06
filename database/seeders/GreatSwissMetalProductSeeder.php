<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\ProductVariationAttribute;
use Carbon\Carbon;

class GreatSwissMetalProductSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $productCategories = [
            'STRUCTURAL STEEL',
            'METAL ROOFING',
            'STEEL FRAMES',
            'CUSTOM FABRICATION',
        ];

        foreach ($productCategories as $category) {
            DB::table('categories')->insert([
                'related_model' => 'products',
                'parent_id' => null,
                'name' => $category,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $productsByCategory = [
            'STRUCTURAL STEEL' => [
                'H-Beam 200x200', 'I-Beam 150x75', 'C-Channel 100x50', 'Angle Bar 50x50x6mm', 'Flat Bar 100x10mm', 'Steel Plate 4x8 10mm'
            ],
            'METAL ROOFING' => [
                'Corrugated GI Sheet 0.5mm', 'Rib-Type Roof Panel 0.6mm', 'Standing Seam Roof Panel 0.7mm'
            ],
            'STEEL FRAMES' => [
                'Steel Door Frame', 'Window Frame 120x50', 'Partition Frame 75x50', 'Steel Truss Assembly'
            ],
            'CUSTOM FABRICATION' => [
                'Custom Spiral Staircase', 'Architectural Canopy', 'Steel Gate (Modern Design)', 'Machine Frame Base', 'Customized Metal Railing'
            ]
        ];

        foreach ($productsByCategory as $categoryName => $products) {
            $category = DB::table('categories')->where('name', $categoryName)->first();

            if (!$category) continue;

            foreach ($products as $productName) {
                DB::table('products')->insert([
                    'slug' => Str::slug($productName),
                    'token' => Str::random(64),
                    'company_id' => 1, // Adjust company_id as needed
                    'category_id' => $category->id,
                    'name' => $productName,
                    'description' => 'High-quality ' . $productName . ' manufactured with precision engineering.',
                    'avatar' => null,
                    'unit_of_measure' => 'pcs',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $product = Product::where('name', $productName)->first();

                $defaultVariation = $product->variations()->create([
                    'name' => 'Default Product Variation',
                    'is_default' => true,
                ]);

                $conditionAttributeId = DB::table('attributes')->where('name', 'Condition')->value('id');

                foreach (['New'] as $condition) {
                    ProductVariationAttribute::create([
                        'product_variation_id' => $defaultVariation->id,
                        'attribute_id' => $conditionAttributeId,
                        'attribute_value_id' => DB::table('attribute_values')->where('value', $condition)->value('id'),
                    ]);
                }
            }
        }
    }
}
