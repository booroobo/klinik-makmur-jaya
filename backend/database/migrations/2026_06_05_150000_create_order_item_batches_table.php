<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_batch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->unique(['order_item_id', 'medicine_batch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_batches');
    }
};
