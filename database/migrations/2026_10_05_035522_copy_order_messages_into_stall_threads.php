<?php

use App\Actions\Chat\CopyOrderMessagesToStallThreads;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $copied = app(CopyOrderMessagesToStallThreads::class)->handle();

        Log::info('Copied order messages into stall threads.', ['copied' => $copied]);
    }

    public function down(): void
    {
        //
    }
};
