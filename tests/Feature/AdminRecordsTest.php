<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Filament\Pages\SystemHealthPage;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\Concerns\CreatesMarketplaceActors;
use Tests\TestCase;

/**
 * The activity log is split by office, records role changes and the account
 * actions that are not plain edits, and the account tools cannot reach your
 * own account by the back door.
 */
class AdminRecordsTest extends TestCase
{
    use CreatesMarketplaceActors;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_cosel_and_ictd_each_read_their_own_part_of_the_activity_log(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $ictd = $this->staff(Role::SuperAdmin);

        foreach ([ActivityLog::AREA_PEOPLE, ActivityLog::AREA_ADMINS, ActivityLog::AREA_SYSTEM] as $area) {
            ActivityLog::query()->create([
                'user_id' => $cosel->id,
                'action' => 'updated',
                'area' => $area,
                'subject_type' => 'test',
                'subject_label' => $area,
            ]);
        }

        $this->assertEqualsCanonicalizing(
            [ActivityLog::AREA_PEOPLE, ActivityLog::AREA_SYSTEM],
            ActivityLog::query()->visibleTo($cosel)->pluck('area')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [ActivityLog::AREA_ADMINS, ActivityLog::AREA_SYSTEM],
            ActivityLog::query()->visibleTo($ictd)->pluck('area')->all(),
        );
    }

    public function test_an_administrator_account_ictd_creates_is_in_ictds_part_with_its_role(): void
    {
        $ictd = $this->staff(Role::SuperAdmin);

        Livewire::actingAs($ictd)
            ->test(CreateUser::class)
            ->fillForm([
                'name' => 'COSEL Account Manager',
                'email' => 'cosel.manager@example.com',
                'roles' => (int) RoleModel::findByName(Role::AccountManager->value)->id,
                'status' => UserStatus::Active->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $manager = User::query()->where('email', 'cosel.manager@example.com')->firstOrFail();
        $rows = ActivityLog::query()
            ->where('subject_type', $manager->getMorphClass())
            ->where('subject_id', $manager->id)
            ->get();

        $this->assertTrue($rows->where('action', 'created')->every(fn (ActivityLog $row): bool => $row->area === ActivityLog::AREA_ADMINS));
        $roleRow = $rows->firstWhere('action', 'role_changed');
        $this->assertNotNull($roleRow);
        $this->assertSame(ActivityLog::AREA_ADMINS, $roleRow->area);
        $this->assertSame(Role::AccountManager->label(), $roleRow->changes['role']['new']);
    }

    public function test_cosel_changing_a_role_is_logged(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $buyer = $this->buyer();

        Livewire::actingAs($cosel)
            ->test(EditUser::class, ['record' => $buyer->getRouteKey()])
            ->fillForm([
                'roles' => (int) RoleModel::findByName(Role::ContentEditor->value)->id,
                'farm_id' => $this->farm()->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $row = ActivityLog::query()->where('action', 'role_changed')->where('subject_id', $buyer->id)->first();

        $this->assertNotNull($row);
        $this->assertSame(ActivityLog::AREA_PEOPLE, $row->area);
        $this->assertSame(['old' => Role::Buyer->label(), 'new' => Role::ContentEditor->label()], $row->changes['role']);
    }

    public function test_sign_out_everywhere_is_logged(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $buyer = $this->buyer();

        Livewire::actingAs($cosel)
            ->test(EditUser::class, ['record' => $buyer->getRouteKey()])
            ->callAction('signOutEverywhere');

        $this->assertTrue(ActivityLog::query()
            ->where('action', 'signed_out_everywhere')
            ->where('subject_id', $buyer->id)
            ->where('area', ActivityLog::AREA_PEOPLE)
            ->exists());
    }

    public function test_turning_two_step_sign_in_off_stops_the_codes_for_everyone(): void
    {
        $ictd = $this->staff(Role::SuperAdmin);
        $cosel = $this->staff(Role::AccountManager);
        $cosel->toggleEmailAuthentication(true);
        SystemSetting::put(SystemSetting::REQUIRE_ADMIN_TWO_STEP, '1');

        Livewire::actingAs($ictd)
            ->test(SystemHealthPage::class)
            ->fillForm(['require_admin_two_step' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse(SystemSetting::requiresAdminTwoStep());
        $this->assertFalse($cosel->fresh()->hasEmailAuthentication());
    }

    public function test_deleting_several_accounts_skips_your_own(): void
    {
        $cosel = $this->staff(Role::AccountManager);
        $buyer = $this->buyer();

        Livewire::actingAs($cosel)
            ->test(ListUsers::class)
            ->callTableBulkAction('delete', [$cosel, $buyer]);

        $this->assertNotSoftDeleted($cosel);
        $this->assertSoftDeleted($buyer);
    }

    private function staff(Role $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles($role);

        return $user;
    }
}
