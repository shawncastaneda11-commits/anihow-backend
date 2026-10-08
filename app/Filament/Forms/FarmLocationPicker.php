<?php

namespace App\Filament\Forms;

use App\Support\FarmPin;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

class FarmLocationPicker
{
    /**
     * Coordinate fields stay in the form so FarmPin bounds still run on save.
     * They are hidden: the map is the only control the editor sees.
     *
     * @return list<Hidden|ViewField>
     */
    public static function fields(): array
    {
        return [
            self::coordinate('latitude'),
            self::coordinate('longitude'),
            Hidden::make('update_address')->default(true),
            Hidden::make('suggested_barangay'),
            Hidden::make('suggested_municipality'),
            Hidden::make('suggested_address'),
            ViewField::make('picker')
                ->hiddenLabel()
                ->dehydrated(false)
                ->view('filament.forms.farm-location-picker')
                ->viewData(function (Component $component, Get $get): array {
                    $base = (string) ($component->getContainer()->getStatePath() ?? '');

                    return [
                        'latitude' => $get('latitude'),
                        'longitude' => $get('longitude'),
                        'latitudePath' => $base !== '' ? $base.'.latitude' : '',
                        'longitudePath' => $base !== '' ? $base.'.longitude' : '',
                        'updateAddressPath' => $base !== '' ? $base.'.update_address' : '',
                        'suggestedBarangayPath' => $base !== '' ? $base.'.suggested_barangay' : '',
                        'suggestedMunicipalityPath' => $base !== '' ? $base.'.suggested_municipality' : '',
                        'suggestedAddressPath' => $base !== '' ? $base.'.suggested_address' : '',
                    ];
                }),
        ];
    }

    private static function coordinate(string $name): Hidden
    {
        return Hidden::make($name)->rules(FarmPin::rules()[$name]);
    }
}
