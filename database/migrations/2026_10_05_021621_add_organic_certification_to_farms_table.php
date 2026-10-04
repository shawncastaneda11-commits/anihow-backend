<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->string('organic_certifier')->nullable();
            $table->string('organic_certificate_no')->nullable();
            $table->date('organic_certified_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table): void {
            $table->dropColumn([
                'organic_certifier',
                'organic_certificate_no',
                'organic_certified_until',
            ]);
        });
    }
};
