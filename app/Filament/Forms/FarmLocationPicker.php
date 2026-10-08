<?php

namespace App\Filament\Forms;

use App\Support\FarmPin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;

class FarmLocationPicker
{
    /**
     * Hidden coordinate inputs plus the Leaflet picker. The inputs stay in the
     * form so FarmPin bounds and "required together" still run on save.
     *
     * @return list<TextInput|ViewField>
     */
    public static function fields(): array
    {
        return [
            self::coordinate('latitude', 'Latitude', 4, 22),
            self::coordinate('longitude', 'Longitude', 116, 127),
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
                    ];
                }),
        ];
    }

    private static function coordinate(string $name, string $label, int $min, int $max): TextInput
    {
        $partner = $name === 'latitude' ? 'longitude' : 'latitude';

        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue($min)
            ->maxValue($max)
            ->nullable()
            ->rules(['required_with:'.$partner])
            ->helperText(FarmPin::HELPER)
            ->extraInputAttributes([
                'style' => 'position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);',
            ]);
    }
}
