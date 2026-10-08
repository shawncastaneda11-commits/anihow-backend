<?php

namespace App\Filament\Resources\Farms\Pages;

use App\Enums\Permission;
use App\Filament\Forms\FarmLocationPicker;
use App\Filament\Resources\Farms\FarmResource;
use App\Models\Farm;
use App\Policies\FarmPolicy;
use App\Support\GoogleMapsLinkResolver;
use App\Support\ImageVariants;
use App\Support\NominatimPlaceSearch;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class EditFarm extends EditRecord
{
    protected static string $resource = FarmResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.farms.profile')
                ->viewData(fn (): array => [
                    'farm' => $this->getRecord(),
                ]),
            $this->getRelationManagersContentComponent(),
        ]);
    }

    protected function resolveRecord(int|string $key): Model
    {
        $record = Farm::query()->whereKey($key)->first();

        if (! $record instanceof Farm) {
            throw (new ModelNotFoundException)->setModel(Farm::class, [$key]);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @return array{places: list<array{label: string, latitude: float, longitude: float}>, message: ?string}
     */
    public function searchFarmPlaces(string $query): array
    {
        $this->authorizeFarmUpdate();

        return app(NominatimPlaceSearch::class)->search($query, (int) auth()->id());
    }

    /**
     * @return array{latitude: ?float, longitude: ?float, message: ?string}
     */
    public function resolveFarmMapLink(string $url): array
    {
        $this->authorizeFarmUpdate();

        return app(GoogleMapsLinkResolver::class)->resolve($url);
    }

    public function editCoverAction(): Action
    {
        return Action::make('editCover')
            ->modalHeading('Edit cover')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Farm $record): array => [
                'cover_photo_path' => $record->cover_photo_path,
            ])
            ->schema([
                ImageVariants::bindUpload(
                    FileUpload::make('cover_photo_path')
                        ->label('Cover photo')
                        ->image()
                        ->disk(config('anihow.listing_disk', 'public'))
                        ->directory('farms')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->helperText('Shown publicly in the app.'),
                    'farms',
                ),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'cover_photo_path' => $this->storedUpload($data['cover_photo_path'] ?? null),
                ]);
            });
    }

    public function editLogoAction(): Action
    {
        return Action::make('editLogo')
            ->modalHeading('Edit logo')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Farm $record): array => [
                'logo_path' => $record->logo_path,
            ])
            ->schema([
                ImageVariants::bindUpload(
                    FileUpload::make('logo_path')
                        ->label('Farm logo')
                        ->image()
                        ->imageCropAspectRatio('1:1')
                        ->imageResizeMode('cover')
                        ->imageResizeTargetWidth('512')
                        ->imageResizeTargetHeight('512')
                        ->disk(config('anihow.listing_disk', 'public'))
                        ->directory('farms/logos')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->helperText('Square image, up to 2 MB. Shown in the app.'),
                    'farms/logos',
                ),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'logo_path' => $this->storedUpload($data['logo_path'] ?? null),
                ]);
            });
    }

    public function editNameAction(): Action
    {
        return Action::make('editName')
            ->modalHeading('Edit name')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Farm $record): array => [
                'name' => $record->name,
                'slug' => $record->slug,
            ])
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(150)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, callable $set): mixed => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(160)
                    ->unique(ignoreRecord: true)
                    ->helperText('Used in article URLs, so two farms can publish a guide with the same title.'),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'name' => $data['name'],
                    'slug' => $data['slug'],
                ]);
            });
    }

    public function editAboutAction(): Action
    {
        return Action::make('editAbout')
            ->modalHeading('About')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Farm $record): array => [
                'description' => $record->description,
            ])
            ->schema([
                Textarea::make('description')
                    ->rows(4)
                    ->helperText('Shown publicly in the app.'),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'description' => $data['description'] ?? null,
                ]);
            });
    }

    public function editContactAction(): Action
    {
        return Action::make('editContact')
            ->modalHeading('Contact & pickup')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Farm $record): array => [
                'contact_person' => $record->contact_person,
                'contact_number' => $record->contact_number,
                'pickup_point' => $record->pickup_point,
                'address' => $record->address,
                'barangay' => $record->barangay,
                'municipality' => $record->municipality,
            ])
            ->schema([
                TextInput::make('contact_person')
                    ->maxLength(150)
                    ->helperText('Shown publicly in the app.'),
                TextInput::make('contact_number')
                    ->tel()
                    ->maxLength(30)
                    ->helperText("Visible only to this farm's farmer-sellers and the Super Admin."),
                TextInput::make('pickup_point')
                    ->maxLength(255)
                    ->helperText('Shown publicly in the app. Where buyers meet sellers.'),
                TextInput::make('address')
                    ->maxLength(255),
                TextInput::make('barangay')
                    ->maxLength(100),
                TextInput::make('municipality')
                    ->maxLength(100),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'contact_person' => $data['contact_person'] ?? null,
                    'contact_number' => $data['contact_number'] ?? null,
                    'pickup_point' => $data['pickup_point'] ?? null,
                    'address' => $data['address'] ?? null,
                    'barangay' => $data['barangay'] ?? null,
                    'municipality' => $data['municipality'] ?? null,
                ]);
            });
    }

    public function editLocationAction(): Action
    {
        return Action::make('editLocation')
            ->modalHeading('Location')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Farm $record): array => [
                'latitude' => $record->latitude,
                'longitude' => $record->longitude,
            ])
            ->schema(FarmLocationPicker::fields())
            ->extraModalFooterActions([
                Action::make('removePin')
                    ->label('Remove pin')
                    ->color('danger')
                    ->action(function (Farm $record): void {
                        $record->update([
                            'latitude' => null,
                            'longitude' => null,
                        ]);
                    })
                    ->cancelParentActions(),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'latitude' => $this->coordinate($data['latitude'] ?? null),
                    'longitude' => $this->coordinate($data['longitude'] ?? null),
                ]);
            });
    }

    public function editFeaturesAction(): Action
    {
        return Action::make('editFeatures')
            ->modalHeading('Farm features')
            ->modalSubmitActionLabel('Save')
            ->fillForm(fn (Farm $record): array => collect(Farm::featureSwitches())
                ->mapWithKeys(fn (array $switch, string $column): array => [
                    $column => (bool) $record->getAttribute($column),
                ])
                ->all())
            ->schema(collect(Farm::featureSwitches())
                ->map(fn (array $switch, string $column): Toggle => Toggle::make($column)
                    ->label($switch['label'])
                    ->helperText($switch['helper']))
                ->values()
                ->all())
            ->action(function (Farm $record, array $data): void {
                $payload = [];

                foreach (array_keys(Farm::featureSwitches()) as $column) {
                    $payload[$column] = array_key_exists($column, $data)
                        ? (bool) $data[$column]
                        : (bool) $record->getAttribute($column);
                }

                $turnedOff = $this->featuresTurnedOff($record, $payload);
                $record->update($payload);
                $this->notifyFeaturesTurnedOff($turnedOff);
            });
    }

    public function editOrganicAction(): Action
    {
        return Action::make('editOrganic')
            ->modalHeading('Organic certification')
            ->modalSubmitActionLabel('Save')
            ->visible(fn (): bool => $this->canManageOrganic())
            ->authorize(fn (): bool => $this->canManageOrganic())
            ->fillForm(fn (Farm $record): array => [
                'organic_certifier' => $record->organic_certifier,
                'organic_certificate_no' => $record->organic_certificate_no,
                'organic_certified_until' => $record->organic_certified_until,
            ])
            ->schema([
                TextInput::make('organic_certifier')
                    ->label('Organic certifier')
                    ->maxLength(150)
                    ->helperText('The body that certified this farm. Leave blank when the farm is not certified.'),
                TextInput::make('organic_certificate_no')
                    ->label('Organic certificate number')
                    ->maxLength(100),
                DatePicker::make('organic_certified_until')
                    ->label('Organic certificate valid until')
                    ->native(false)
                    ->helperText('A listing may say certified organic only while this date is today or later, and the certifier and number are both filled in.'),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'organic_certifier' => $data['organic_certifier'] ?? null,
                    'organic_certificate_no' => $data['organic_certificate_no'] ?? null,
                    'organic_certified_until' => $data['organic_certified_until'] ?? null,
                ]);
            });
    }

    public function editStatusAction(): Action
    {
        return Action::make('editStatus')
            ->modalHeading('Status')
            ->modalSubmitActionLabel('Save')
            ->visible(fn (): bool => $this->canManageStatus())
            ->authorize(fn (): bool => $this->canManageStatus())
            ->fillForm(fn (Farm $record): array => [
                'is_active' => $record->is_active,
            ])
            ->schema([
                Toggle::make('is_active')
                    ->label('Active'),
            ])
            ->action(function (Farm $record, array $data): void {
                $record->update([
                    'is_active' => (bool) ($data['is_active'] ?? false),
                ]);
            });
    }

    private function authorizeFarmUpdate(): void
    {
        $farm = $this->getRecord();

        abort_unless(
            $farm instanceof Farm && (auth()->user()?->can('update', $farm) ?? false),
            403,
        );
    }

    private function canManageOrganic(): bool
    {
        $user = auth()->user();
        $farm = $this->getRecord();

        return $user !== null
            && $farm instanceof Farm
            && app(FarmPolicy::class)->manageOrganicCertification($user, $farm);
    }

    private function canManageStatus(): bool
    {
        return auth()->user()?->can(Permission::ManageFarms->value) ?? false;
    }

    private function storedUpload(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = collect($value)->first(fn (mixed $item): bool => is_string($item) && $item !== '');
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function coordinate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, bool>  $payload
     * @return list<array{label: string, helper: string}>
     */
    private function featuresTurnedOff(Farm $record, array $payload): array
    {
        $turnedOff = [];

        foreach (Farm::featureSwitches() as $column => $switch) {
            $wasOn = (bool) $record->getAttribute($column);
            $staysOn = (bool) ($payload[$column] ?? $wasOn);

            if ($wasOn && ! $staysOn) {
                $turnedOff[] = $switch;
            }
        }

        return $turnedOff;
    }

    /**
     * @param  list<array{label: string, helper: string}>  $turnedOff
     */
    private function notifyFeaturesTurnedOff(array $turnedOff): void
    {
        if ($turnedOff === []) {
            return;
        }

        $names = collect($turnedOff)->pluck('label')->join(', ');
        $effects = collect($turnedOff)
            ->map(fn (array $switch): string => $switch['label'].': '.$switch['helper'])
            ->join("\n");

        Notification::make()
            ->warning()
            ->title($names.' turned off')
            ->body($effects)
            ->send();
    }
}
