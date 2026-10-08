<?php

namespace App\Filament\Resources\Farms\Pages;

use App\Enums\Permission;
use App\Filament\Resources\Farms\FarmResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ListFarms extends ListRecords
{
    protected static string $resource = FarmResource::class;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user !== null && ! $user->can(Permission::ManageFarms->value) && $user->farm_id) {
            $this->redirect(FarmResource::getUrl('edit', ['record' => $user->farm_id]));

            return;
        }

        parent::mount();
    }

    public function content(Schema $schema): Schema
    {
        $user = auth()->user();

        if ($user !== null && ! $user->can(Permission::ManageFarms->value) && $user->farm_id === null) {
            return $schema->components([
                View::make('filament.farms.unassigned'),
            ]);
        }

        return parent::content($schema);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
