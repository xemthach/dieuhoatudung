<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Hash;

class Profile extends Page
{
    protected string $view = 'filament.pages.profile';

    protected static bool $shouldRegisterNavigation = false;

    public string $name     = '';
    public string $email    = '';
    public ?string $avatar_url = null;

    // Password change fields
    public string $current_password    = '';
    public string $new_password        = '';
    public string $new_password_confirm = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->name       = $user->name;
        $this->email      = $user->email;
        $this->avatar_url = $user->avatar_url;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    // ── Save basic info ──────────────────────────────────────────────────
    public function saveProfile(): void
    {
        $this->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . auth()->id(),
            'avatar_url' => 'nullable|string',
        ]);

        auth()->user()->update([
            'name'       => $this->name,
            'email'      => $this->email,
            'avatar_url' => $this->avatar_url,
        ]);

        Notification::make()
            ->title('Đã cập nhật hồ sơ')
            ->success()
            ->send();
    }

    // ── Change password ──────────────────────────────────────────────────
    public function changePassword(): void
    {
        $this->validate([
            'current_password'     => 'required',
            'new_password'         => 'required|min:8|confirmed:new_password_confirm',
            'new_password_confirm' => 'required',
        ]);

        if (!Hash::check($this->current_password, auth()->user()->password)) {
            $this->addError('current_password', 'Mật khẩu hiện tại không đúng.');
            return;
        }

        auth()->user()->update(['password' => Hash::make($this->new_password)]);

        $this->current_password     = '';
        $this->new_password         = '';
        $this->new_password_confirm = '';

        Notification::make()
            ->title('Đã đổi mật khẩu thành công')
            ->success()
            ->send();
    }

    public static function getNavigationLabel(): string
    {
        return 'Hồ sơ của tôi';
    }
}
