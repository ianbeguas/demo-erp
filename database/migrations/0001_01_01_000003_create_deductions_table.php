<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., SSS, PhilHealth
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(false);
            $table->enum('deduction_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('default_value', 10, 2)->nullable(); // value can be fixed amount or percentage
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
