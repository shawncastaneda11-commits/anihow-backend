<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harvest_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('farmer_seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('farm_id')->constrained();
            $table->foreignId('crop_type_id')->constrained();
            $table->string('unit');
            $table->boolean('is_value_added');
            $table->date('harvested_on');
            $table->decimal('quantity_harvested', 10, 2);
            $table->decimal('quantity_rejected', 10, 2)->default(0);
            $table->decimal('quantity_good', 10, 2);
            $table->string('rejection_reason')->nullable();
            $table->string('rejection_note', 255)->nullable();
            $table->decimal('price_per_unit', 12, 4);
            $table->decimal('production_cost', 12, 2)->nullable();
            $table->json('cost_breakdown')->nullable();
            $table->string('kind');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['farm_id', 'harvested_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harvest_records');
    }
};
