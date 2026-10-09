<?php

use App\Models\Listing;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Listings that already ended with stock were never going to get a first
     * notice. Mark them so the scheduler does not flood sellers on deploy.
     */
    public function up(): void
    {
        Listing::query()
            ->expired()
            ->where('quantity_available', '>', 0)
            ->update(['expired_stock_notified_at' => now()]);
    }

    public function down(): void
    {
        Listing::query()
            ->expired()
            ->where('quantity_available', '>', 0)
            ->update(['expired_stock_notified_at' => null]);
    }
};
