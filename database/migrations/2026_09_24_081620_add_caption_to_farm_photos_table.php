<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farm_photos', function (Blueprint $table) {
            $table->string('caption')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('farm_photos', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
