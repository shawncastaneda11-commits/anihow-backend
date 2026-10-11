<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->decimal('stock_baseline', 10, 2)->nullable()->after('quantity_available');
        });

        $latestGood = DB::table('harvest_records')
            ->whereIn('kind', ['opening', 'initial', 'added', 'actual'])
            ->whereNotNull('listing_id')
            ->orderBy('id')
            ->pluck('quantity_good', 'listing_id');

        DB::table('listings')->select(['id', 'quantity_available'])->orderBy('id')->chunkById(500, function ($listings) use ($latestGood): void {
            foreach ($listings as $listing) {
                $available = (float) $listing->quantity_available;
                $baseline = max($available, (float) ($latestGood[$listing->id] ?? 0));

                DB::table('listings')->where('id', $listing->id)->update(['stock_baseline' => $baseline]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn('stock_baseline');
        });
    }
};
