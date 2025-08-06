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
        Schema::create('goods_receipt_detail_remarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_detail_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('goods_receipt_serial_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->boolean('is_serial')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_detail_remarks');
    }
};
