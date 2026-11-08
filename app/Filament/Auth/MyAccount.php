<?php

namespace App\Filament\Auth;

use App\Models\SystemSetting;
use App\Models\User;
use Filament\Auth\Pages\EditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

/**
 * "My account", from the account circle: your name, your password, and
 * two-step sign-in. The email is changed by whoever manages the account
 * (COSEL for people, ICTD for administrators), not here. Two-step sign-in is
 * optional unless ICTD requires it for administrators.
 */
class MyAccount extends EditProfile
{
    protected static ?string $title = 'My account';

    public static function getLabel(): string
    {
        return 'My account';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                TextInput::make('email')
                    ->label('Email')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText(fn (): string => $this->getUser() instanceof User && $this->getUser()->isAdministrator()
                        ? 'ICTD changes administrator emails.'
                        : 'COSEL changes this for you.'),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
            ]);
    }

    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        $section = parent::getMultiFactorAuthenticationContentComponent();

        if (! $section instanceof Section) {
            return $section;
        }

        $user = $this->getUser();
        $required = SystemSetting::requiresAdminTwoStep() && $user instanceof User && $user->isAdministrator();

        return $section
            ->heading('Two-step sign-in')
            ->description($required
                ? 'ICTD requires two-step sign-in for administrators, so it stays on. Ask ICTD if you no longer use this email.'
                : 'Optional for you. A code is sent to your email each time you sign in. Turn it on only if you can open that email when you sign in.')
            ->afterHeader($required ? [Text::make('Required by ICTD')->badge()->color('warning')] : []);
    }
}
