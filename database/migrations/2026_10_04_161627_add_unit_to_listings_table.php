<?php

use App\Support\Pricing\UnitConverter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->string('unit')->default('kg')->after('crop_type_id');
        });

        app(UnitConverter::class)->backfillListingUnits();
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn('unit');
        });
    }
};
