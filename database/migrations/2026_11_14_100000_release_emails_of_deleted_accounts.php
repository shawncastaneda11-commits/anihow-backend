<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Accounts deleted from the CMS kept their email, so nobody could sign up
 * or be given an account with it again. Each one now holds a placeholder,
 * as an anonymised account already does. The real emails are not restored
 * on rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->where('email', 'not like', 'deleted-%@anihow.invalid')
            ->orderBy('id')
            ->select('id')
            ->each(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update(['email' => "deleted-{$user->id}@anihow.invalid"]);
            });
    }

    public function down(): void
    {
        //
    }
};
