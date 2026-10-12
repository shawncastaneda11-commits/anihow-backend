<?php

namespace App\Enums;

/**
 * Government IDs a buyer can submit for verification.
 */
enum IdDocumentType: string
{
    case NationalId = 'national_id';
    case DriversLicense = 'drivers_license';
    case Passport = 'passport';
    case Umid = 'umid';
    case PostalId = 'postal_id';
    case OtherGovernment = 'other_government';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => 'National ID (PhilSys)',
            self::DriversLicense => "Driver's license",
            self::Passport => 'Passport',
            self::Umid => 'UMID',
            self::PostalId => 'Postal ID',
            self::OtherGovernment => 'Other government ID',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
