<?php

namespace App\Filament\Pages;

use App\Services\Media\MediaDiskService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class Profile extends Page
{
    protected string $view = 'filament.pages.profile';

    protected static bool $shouldRegisterNavigation = false;

    // Profile fields
    public string $name = '';
    public string $email = '';
    public ?string $avatar_url = null;

    // Password change fields
    public string $current_password = '';
    public string $new_password = '';
    public string $new_password_confirm = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->avatar_url = $user->avatar_url;
    }

    public function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public static function getNavigationLabel(): string
    {
        return 'Hồ sơ của tôi';
    }

    public function getTitle(): string
    {
        return 'Hồ sơ của tôi';
    }

    // ── Save basic info ──────────────────────────────────────────────────
    public function saveProfile(): void
    {
        $this->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . Auth::id(),
        ]);

        $user = Auth::user();
        $user->update([
            'name'  => $this->name,
            'email' => $this->email,
        ]);

        Notification::make()
            ->title('Đã cập nhật hồ sơ')
            ->success()
            ->send();
    }

    // ── Upload / change avatar ───────────────────────────────────────────
    public function saveAvatar(): void
    {
        $this->validate([
            'avatar_url' => 'nullable|url|max:500',
        ]);

        Auth::user()->update([
            'avatar_url' => $this->avatar_url ?: null,
        ]);

        Notification::make()
            ->title('Đã cập nhật ảnh đại diện')
            ->success()
            ->send();
    }

    public function removeAvatar(): void
    {
        $this->avatar_url = null;

        Auth::user()->update([
            'avatar_url' => null,
        ]);

        Notification::make()
            ->title('Đã xóa ảnh đại diện')
            ->success()
            ->send();
    }

    // ── Change password ──────────────────────────────────────────────────
    public function changePassword(): void
    {
        $this->validate([
            'current_password'     => 'required',
            'new_password'         => 'required|min:8',
            'new_password_confirm' => 'required|same:new_password',
        ], [
            'current_password.required'     => 'Vui lòng nhập mật khẩu hiện tại.',
            'new_password.required'         => 'Vui lòng nhập mật khẩu mới.',
            'new_password.min'              => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'new_password_confirm.required' => 'Vui lòng xác nhận mật khẩu mới.',
            'new_password_confirm.same'     => 'Mật khẩu xác nhận không khớp.',
        ]);

        // Verify current password
        if (!Hash::check($this->current_password, Auth::user()->password)) {
            $this->addError('current_password', 'Mật khẩu hiện tại không đúng.');
            return;
        }

        // User model has 'password' => 'hashed' cast, so pass plain text.
        // Do NOT use Hash::make() here — the cast handles hashing automatically.
        Auth::user()->update([
            'password' => $this->new_password,
        ]);

        // Clear form
        $this->current_password = '';
        $this->new_password = '';
        $this->new_password_confirm = '';

        Notification::make()
            ->title('Đã đổi mật khẩu thành công')
            ->body('Mật khẩu mới có hiệu lực ngay lập tức.')
            ->success()
            ->send();
    }
}
