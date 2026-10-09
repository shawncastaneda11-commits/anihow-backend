<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_removals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('farmer_seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('farm_id')->constrained();
            $table->foreignId('crop_type_id')->constrained();
            $table->string('unit');
            $table->boolean('is_value_added');
            $table->decimal('quantity', 10, 2);
            $table->string('reason');
            $table->string('note', 255)->nullable();
            $table->decimal('price_per_unit', 12, 4);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['farm_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_removals');
    }
};
