<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stall_messages', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('listing_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
            $table->string('listing_title')->nullable()->after('listing_id');
            $table->decimal('listing_price_per_unit', 12, 4)->nullable()->after('listing_title');
            $table->string('listing_unit')->nullable()->after('listing_price_per_unit');
            $table->string('listing_thumbnail_path')->nullable()->after('listing_unit');
            $table->foreignId('source_order_message_id')
                ->nullable()
                ->unique()
                ->after('listing_thumbnail_path')
                ->constrained('order_messages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stall_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_order_message_id');
            $table->dropConstrainedForeignId('listing_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn([
                'listing_title',
                'listing_price_per_unit',
                'listing_unit',
                'listing_thumbnail_path',
            ]);
        });
    }
};
