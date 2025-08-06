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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete(); // Which company it belongs to
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete(); // Which invoice it belongs to
            $table->string('number')->nullable()->unique(); // Unique goods receipt number
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete(); // Who created the shipment
            $table->enum('status', ['pending', 'partially-delivered', 'fully-delivered'])->default('pending');
            $table->text('file_path')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
