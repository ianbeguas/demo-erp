<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE warehouse_stock_adjustments MODIFY reason ENUM('damaged', 'lost', 'count-correction', 'other', 'add-stock', 'found') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE warehouse_stock_adjustments MODIFY reason ENUM('damaged', 'lost', 'count-correction', 'other') NULL");
    }
};
