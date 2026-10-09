<?php

use App\Actions\Listings\BackfillOpeningHarvestRecords;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->boolean('needs_actual_harvest')->default(false);
            $table->timestamp('stock_tracked_since')->nullable();
            $table->timestamp('harvest_reminded_at')->nullable();
            $table->timestamp('expired_stock_notified_at')->nullable();
        });

        app(BackfillOpeningHarvestRecords::class)->handle();
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn([
                'needs_actual_harvest',
                'stock_tracked_since',
                'harvest_reminded_at',
                'expired_stock_notified_at',
            ]);
        });
    }
};
