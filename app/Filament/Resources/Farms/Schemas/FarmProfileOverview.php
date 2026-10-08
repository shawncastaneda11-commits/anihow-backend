<?php

namespace App\Filament\Resources\Farms\Schemas;

use App\Filament\Resources\Farms\Pages\EditFarm;
use App\Models\Farm;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

class FarmProfileOverview
{
    /**
     * @return list<Group>
     */
    public static function components(EditFarm $page): array
    {
        return [
            Group::make([
                self::about($page),
                self::contact($page),
            ])->columnSpan(['default' => 1, 'lg' => 2]),
            Group::make([
                self::location($page),
                self::features($page),
                self::organic($page),
            ])->columnSpan(['default' => 1, 'lg' => 1]),
        ];
    }

    private static function about(EditFarm $page): Section
    {
        return Section::make('About')
            ->headerActions([
                self::editLink('editAbout', 'Edit about'),
            ])
            ->schema([
                TextEntry::make('description')
                    ->hiddenLabel()
                    ->extraAttributes(['class' => 'whitespace-pre-wrap'])
                    ->state(function () use ($page): string {
                        $description = self::farm($page)->description;

                        return filled($description) ? (string) $description : 'No description yet';
                    }),
            ]);
    }

    private static function contact(EditFarm $page): Section
    {
        return Section::make('Contact & pickup')
            ->headerActions([
                self::editLink('editContact', 'Edit contact'),
            ])
            ->schema([
                TextEntry::make('contact_person')
                    ->label('Contact person')
                    ->icon(Heroicon::OutlinedUser)
                    ->state(fn (): string => self::filled(self::farm($page)->contact_person, 'No contact person yet.')),
                TextEntry::make('contact_number')
                    ->label('Contact number')
                    ->icon(Heroicon::OutlinedPhone)
                    ->helperText("Visible only to this farm's farmer-sellers and the Super Admin.")
                    ->state(fn (): string => self::filled(self::farm($page)->contact_number, 'No contact number yet.')),
                TextEntry::make('pickup_point')
                    ->label('Pickup point')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->state(fn (): string => self::filled(self::farm($page)->pickup_point, 'No pickup point yet.')),
                TextEntry::make('address_line')
                    ->label('Address')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->state(function () use ($page): string {
                        $line = self::farm($page)->addressLine();

                        return $line !== '' ? $line : 'No address yet.';
                    }),
            ]);
    }

    private static function location(EditFarm $page): Section
    {
        return Section::make('Location')
            ->headerActions([
                Action::make('openEditLocation')
                    ->label('Edit location')
                    ->icon(Heroicon::OutlinedPencil)
                    ->color('gray')
                    ->link()
                    ->alpineClickHandler("\$wire.mountAction('editLocation')"),
            ])
            ->schema([
                View::make('filament.farms.location-preview')
                    ->viewData(fn (): array => [
                        'farm' => self::farm($page),
                    ]),
            ]);
    }

    private static function features(EditFarm $page): Section
    {
        $rows = [];

        foreach (Farm::featureSwitches() as $column => $switch) {
            $rows[] = TextEntry::make($column)
                ->label($switch['label'])
                ->badge()
                ->color(fn () => self::farm($page)->{$column} ? 'success' : 'gray')
                ->state(fn (): string => self::farm($page)->{$column} ? 'On' : 'Off');
        }

        return Section::make('Farm features')
            ->headerActions([
                self::editLink('editFeatures', 'Edit features'),
            ])
            ->schema($rows);
    }

    private static function organic(EditFarm $page): Section
    {
        return Section::make('Organic certification')
            ->headerActions([
                self::editLink('editOrganic', 'Edit organic certification')
                    ->visible(fn (): bool => $page->canManageOrganic()),
            ])
            ->schema([
                TextEntry::make('organic_certifier')
                    ->label('Certifier')
                    ->visible(fn (): bool => self::farm($page)->isOrganicCertified())
                    ->state(fn (): string => (string) self::farm($page)->organic_certifier),
                TextEntry::make('organic_certificate_no')
                    ->label('Certificate no.')
                    ->visible(fn (): bool => self::farm($page)->isOrganicCertified())
                    ->state(fn (): string => (string) self::farm($page)->organic_certificate_no),
                TextEntry::make('organic_certified_until')
                    ->label('Valid until')
                    ->visible(fn (): bool => self::farm($page)->isOrganicCertified())
                    ->state(fn (): string => self::farm($page)->organic_certified_until?->toFormattedDateString() ?? ''),
                TextEntry::make('organic_status')
                    ->hiddenLabel()
                    ->badge()
                    ->color('gray')
                    ->visible(fn (): bool => ! self::farm($page)->isOrganicCertified())
                    ->state('Not certified'),
            ]);
    }

    private static function editLink(string $action, string $accessibleName): Action
    {
        return Action::make('open'.ucfirst($action))
            ->label('Edit')
            ->icon(Heroicon::OutlinedPencil)
            ->color('gray')
            ->link()
            ->extraAttributes([
                'aria-label' => $accessibleName,
            ])
            ->alpineClickHandler("\$wire.mountAction('{$action}')");
    }

    private static function farm(EditFarm $page): Farm
    {
        $farm = $page->getRecord();

        return $farm instanceof Farm ? $farm : new Farm;
    }

    private static function filled(mixed $value, string $empty): string
    {
        return is_string($value) && trim($value) !== '' ? $value : $empty;
    }
}
