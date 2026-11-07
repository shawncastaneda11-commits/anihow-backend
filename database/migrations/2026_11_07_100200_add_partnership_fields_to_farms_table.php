<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COSEL's partnership record for each partner farm: when the partnership
 * started and its notes (agreement, contact history). Not shown in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->date('partnered_since')->nullable()->after('contact_number');
            $table->text('partnership_notes')->nullable()->after('partnered_since');
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropColumn(['partnered_since', 'partnership_notes']);
        });
    }
};
