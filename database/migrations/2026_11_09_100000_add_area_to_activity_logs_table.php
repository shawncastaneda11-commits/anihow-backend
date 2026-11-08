<?php

use App\Enums\Role;
use App\Models\CropType;
use App\Models\CropTypeUnitGuard;
use App\Models\FaqEntry;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the activity log by office: "people" rows (accounts, farms,
 * listings) are COSEL's, "admins" rows (administrator accounts) are ICTD's,
 * and "system" rows (crop types, system FAQ, settings) are seen by both.
 * Existing rows are sorted the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->string('area', 20)->default('people')->after('action');
            $table->index('area');
        });

        DB::table('activity_logs')
            ->whereIn('subject_type', [
                (new SystemSetting)->getMorphClass(),
                (new CropType)->getMorphClass(),
                (new CropTypeUnitGuard)->getMorphClass(),
            ])
            ->update(['area' => 'system']);

        $systemFaq = DB::table('faq_entries')->whereNull('farm_id')->pluck('id');

        DB::table('activity_logs')
            ->where('subject_type', (new FaqEntry)->getMorphClass())
            ->whereIn('subject_id', $systemFaq)
            ->update(['area' => 'system']);

        $admins = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('roles.name', array_column(Role::adminRoles(), 'value'))
            ->pluck('model_has_roles.model_id');

        DB::table('activity_logs')
            ->where('subject_type', (new User)->getMorphClass())
            ->whereIn('subject_id', $admins)
            ->update(['area' => 'admins']);
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropIndex(['area']);
            $table->dropColumn('area');
        });
    }
};
