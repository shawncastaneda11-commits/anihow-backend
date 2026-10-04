<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stall_messages', function (Blueprint $table) {
            $table->text('body')->nullable()->change();
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_mime')->nullable()->after('attachment_path');
            $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->string('attachment_name', 120)->nullable()->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('stall_messages', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path',
                'attachment_mime',
                'attachment_size',
                'attachment_name',
            ]);
            $table->text('body')->nullable(false)->change();
        });
    }
};
