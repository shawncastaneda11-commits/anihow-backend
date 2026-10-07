<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Missing composites for the hot reads. Existing migrations are left alone.
 *
 *   orders_status_completed_at_index
 *     AnalyticsService: completed orders by completed_at (SA / windowed).
 *
 *   orders_farmer_seller_id_status_completed_at_index
 *     Farmer order lists + farmer-scoped analytics (status + completed_at).
 *
 *   orders_farm_id_status_completed_at_index
 *     Farm-scoped analytics (status + completed_at). farm_id + completed_at
 *     already exists; this adds status so Completed-only scans stay tight.
 *
 *   orders_status_source_created_at_index
 *     Stale-order sweep: placed + app + created_at, reminder_sent_at IS NULL.
 *
 *   order_items_order_id_crop_type_id_index
 *     AnalyticsService itemQuery: join orders then group by crop_type_id.
 *
 *   farm_announcements_farm_id_starts_at_ends_at_index
 *     Active farm announcements (farm_id + starts_at/ends_at).
 *
 *   farm_announcements_notified_at_starts_at_index
 *     announcements:notify-due (notified_at IS NULL + starts_at).
 *
 *   faq_entries_farm_id_is_active_moderated_at_index
 *     FaqResponder: farm_id + is_active + moderated_at IS NULL.
 *
 *   shop_favorites_farmer_seller_id_index
 *     Reverse lookup (buyer_id + farmer_seller_id unique already covers the buyer).
 *
 * Listings already have (status, is_active, crop_type_id), (farm_id, status),
 * (farmer_seller_id, status). in_app_notifications already has (user_id, read_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['status', 'completed_at'], 'orders_status_completed_at_index');
            $table->index(
                ['farmer_seller_id', 'status', 'completed_at'],
                'orders_farmer_seller_id_status_completed_at_index',
            );
            $table->index(
                ['farm_id', 'status', 'completed_at'],
                'orders_farm_id_status_completed_at_index',
            );
            $table->index(
                ['status', 'source', 'created_at'],
                'orders_status_source_created_at_index',
            );
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->index(['order_id', 'crop_type_id'], 'order_items_order_id_crop_type_id_index');
        });

        Schema::table('farm_announcements', function (Blueprint $table): void {
            $table->index(
                ['farm_id', 'starts_at', 'ends_at'],
                'farm_announcements_farm_id_starts_at_ends_at_index',
            );
            $table->index(
                ['notified_at', 'starts_at'],
                'farm_announcements_notified_at_starts_at_index',
            );
        });

        Schema::table('faq_entries', function (Blueprint $table): void {
            $table->index(
                ['farm_id', 'is_active', 'moderated_at'],
                'faq_entries_farm_id_is_active_moderated_at_index',
            );
        });

        Schema::table('shop_favorites', function (Blueprint $table): void {
            $table->index('farmer_seller_id', 'shop_favorites_farmer_seller_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_status_completed_at_index');
            $table->dropIndex('orders_farmer_seller_id_status_completed_at_index');
            $table->dropIndex('orders_farm_id_status_completed_at_index');
            $table->dropIndex('orders_status_source_created_at_index');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_order_id_crop_type_id_index');
        });

        Schema::table('farm_announcements', function (Blueprint $table): void {
            $table->dropIndex('farm_announcements_farm_id_starts_at_ends_at_index');
            $table->dropIndex('farm_announcements_notified_at_starts_at_index');
        });

        Schema::table('faq_entries', function (Blueprint $table): void {
            $table->dropIndex('faq_entries_farm_id_is_active_moderated_at_index');
        });

        Schema::table('shop_favorites', function (Blueprint $table): void {
            $table->dropIndex('shop_favorites_farmer_seller_id_index');
        });
    }
};
