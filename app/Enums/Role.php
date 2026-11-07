<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case AccountManager = 'account_manager';
    case ContentEditor = 'content_editor';
    case FarmerSeller = 'farmer_seller';
    case Buyer = 'buyer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AccountManager => 'Account Manager',
            self::ContentEditor => 'Content Editor',
            self::FarmerSeller => 'Farmer-Seller',
            self::Buyer => 'Buyer',
        };
    }

    /**
     * Roles that sign in to the Filament panel rather than the Android app.
     *
     * @return list<self>
     */
    public static function panelRoles(): array
    {
        return [self::SuperAdmin, self::AccountManager, self::ContentEditor];
    }

    /**
     * Administrator roles. Only an account that may manage administrators
     * (the ICTD Super Admin) creates, edits, or suspends these.
     *
     * @return list<self>
     */
    public static function adminRoles(): array
    {
        return [self::SuperAdmin, self::AccountManager];
    }

    public function isAdmin(): bool
    {
        return in_array($this, self::adminRoles(), true);
    }

    /**
     * Roles that are scoped to a single farm and therefore require farm_id.
     *
     * @return list<self>
     */
    public static function farmScopedRoles(): array
    {
        return [self::ContentEditor, self::FarmerSeller];
    }

    public function requiresFarm(): bool
    {
        return in_array($this, self::farmScopedRoles(), true);
    }

    public function usesPanel(): bool
    {
        return in_array($this, self::panelRoles(), true);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role): array => [$role->value => $role->label()])
            ->all();
    }
}
