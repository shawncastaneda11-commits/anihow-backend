<?php

namespace App\Filament\Resources\Farms\RelationManagers;

use App\Models\Farm;
use App\Models\FarmPhoto;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A farm's gallery photos. Visible when the viewer can update that farm:
 * ManageOwnFarmProfile on a matching farm_id, or ManageFarms for any farm.
 */
class FarmPhotosRelationManager extends RelationManager
{
    protected static string $relationship = 'photos';

    protected static ?string $title = 'Farm photos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user !== null && $user->can('update', $ownerRecord);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('path')
                    ->label('Photo')
                    ->image()
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->directory(fn (): string => 'farms/'.$this->getOwnerRecord()->getKey())
                    ->visibility('public')
                    ->required()
                    ->maxSize(2048)
                    ->columnSpanFull(),
                TextInput::make('caption')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('caption')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ImageColumn::make('path')
                    ->label('Photo')
                    ->disk(config('anihow.listing_disk', 'public'))
                    ->square(),
                TextColumn::make('caption')
                    ->placeholder('—')
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $this->assertCanWrite();

                        /** @var Farm $farm */
                        $farm = $this->getOwnerRecord();
                        $data['sort_order'] = ((int) $farm->photos()->max('sort_order')) + 1;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->before(fn () => $this->assertCanWrite()),
                DeleteAction::make()
                    ->before(fn () => $this->assertCanWrite()),
            ]);
    }

    private function assertCanWrite(): void
    {
        /** @var Farm $farm */
        $farm = $this->getOwnerRecord();

        abort_unless(
            auth()->user()?->can('createForFarm', [FarmPhoto::class, $farm]) ?? false,
            403,
        );
    }
}
