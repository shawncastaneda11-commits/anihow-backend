<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Resources\FaqEntries\FaqEntryResource;
use App\Models\FaqEntry;
use App\Models\Farm;
use App\Models\User;
use Database\Seeders\FaqEntrySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class FaqEntryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(FaqEntrySeeder::class);
    }

    public function test_a_content_editor_can_manage_own_farm_rows_but_not_system_or_other_farm_rows(): void
    {
        [$farmA, $farmB] = $this->twoFarms();
        $editorA = $this->staff(Role::ContentEditor, $farmA);
        $editorB = $this->staff(Role::ContentEditor, $farmB);

        $system = FaqEntry::query()->whereNull('farm_id')->where('intent_key', 'walk_in')->firstOrFail();
        $own = FaqEntry::factory()
            ->forFarm($farmA)
            ->forIntent('shed_hours')
            ->forRoles([Role::FarmerSeller->value])
            ->create();
        $other = FaqEntry::factory()
            ->forFarm($farmB)
            ->forIntent('shed_hours')
            ->forRoles([Role::FarmerSeller->value])
            ->create();
        $buyerFacing = FaqEntry::factory()
            ->forFarm($farmA)
            ->forIntent('pickup')
            ->forRoles([Role::Buyer->value])
            ->create();

        $this->assertTrue(Gate::forUser($editorA)->allows('create', FaqEntry::class));
        $this->assertTrue(Gate::forUser($editorA)->allows('view', $system));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $system));
        $this->assertTrue(Gate::forUser($editorA)->denies('delete', $system));
        $this->assertTrue(Gate::forUser($editorA)->allows('view', $own));
        $this->assertTrue(Gate::forUser($editorA)->allows('update', $own));
        $this->assertTrue(Gate::forUser($editorA)->allows('delete', $own));
        $this->assertTrue(Gate::forUser($editorA)->denies('view', $other));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $other));
        $this->assertTrue(Gate::forUser($editorA)->denies('delete', $other));
        $this->assertTrue(Gate::forUser($editorA)->denies('update', $buyerFacing));
        $this->assertTrue(Gate::forUser($editorB)->denies('update', $own));
    }

    public function test_a_content_editor_sees_system_rows_and_own_farm_rows_in_the_panel(): void
    {
        [$farmA, $farmB] = $this->twoFarms();
        $editorA = $this->staff(Role::ContentEditor, $farmA);
        $system = FaqEntry::query()->whereNull('farm_id')->where('intent_key', 'walk_in')->firstOrFail();
        $own = FaqEntry::factory()->forFarm($farmA)->forIntent('shed_hours')->create();
        $other = FaqEntry::factory()->forFarm($farmB)->forIntent('shed_hours')->create();

        $this->actingAs($editorA);

        $visible = FaqEntryResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($visible->contains($system->id));
        $this->assertTrue($visible->contains($own->id));
        $this->assertFalse($visible->contains($other->id));
    }

    public function test_a_super_admin_can_manage_system_rows_but_not_farm_rows(): void
    {
        [$farmA] = $this->twoFarms();
        $admin = $this->staff(Role::SuperAdmin);
        $system = FaqEntry::query()->whereNull('farm_id')->where('intent_key', 'pickup')->firstOrFail();
        $farmRow = FaqEntry::factory()->forFarm($farmA)->forIntent('shed_hours')->create();

        $this->assertTrue(Gate::forUser($admin)->allows('create', FaqEntry::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $system));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $system));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $system));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $farmRow));
        $this->assertTrue(Gate::forUser($admin)->denies('update', $farmRow));
        $this->assertTrue(Gate::forUser($admin)->denies('delete', $farmRow));
    }

    /**
     * @return array{0: Farm, 1: Farm}
     */
    private function twoFarms(): array
    {
        return [
            Farm::factory()->create(['name' => 'Farm A']),
            Farm::factory()->create(['name' => 'Farm B']),
        ];
    }

    private function staff(Role $role, ?Farm $farm = null): User
    {
        $user = User::factory()->create(['farm_id' => $farm?->id]);
        $user->syncRoles($role);

        return $user;
    }
}
