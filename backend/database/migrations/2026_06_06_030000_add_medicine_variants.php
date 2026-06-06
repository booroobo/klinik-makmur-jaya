<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicines', function (Blueprint $table): void {
            $table->boolean('has_variants')->default(false)->after('price');
        });

        Schema::create('medicine_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->string('sku')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['medicine_id', 'name']);
        });

        Schema::table('medicine_batches', function (Blueprint $table): void {
            $table->foreignId('medicine_variant_id')
                ->nullable()
                ->after('medicine_id')
                ->constrained('medicine_variants')
                ->restrictOnDelete();
            $table->index(['medicine_id', 'medicine_variant_id', 'expired_date']);
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique(['cart_id', 'medicine_id']);
            $table->foreignId('medicine_variant_id')
                ->nullable()
                ->after('medicine_id')
                ->constrained('medicine_variants')
                ->restrictOnDelete();
            $table->unique(['cart_id', 'medicine_id', 'medicine_variant_id']);
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('medicine_variant_id')
                ->nullable()
                ->after('medicine_id')
                ->constrained('medicine_variants')
                ->nullOnDelete();
            $table->string('variant_name')->nullable()->after('medicine_name');
            $table->decimal('variant_price', 12, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('medicine_variant_id');
            $table->dropColumn(['variant_name', 'variant_price']);
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropUnique(['cart_id', 'medicine_id', 'medicine_variant_id']);
            $table->dropConstrainedForeignId('medicine_variant_id');
            $table->unique(['cart_id', 'medicine_id']);
        });

        Schema::table('medicine_batches', function (Blueprint $table): void {
            $table->dropIndex(['medicine_id', 'medicine_variant_id', 'expired_date']);
            $table->dropConstrainedForeignId('medicine_variant_id');
        });

        Schema::dropIfExists('medicine_variants');

        Schema::table('medicines', function (Blueprint $table): void {
            $table->dropColumn('has_variants');
        });
    }
};
