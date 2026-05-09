<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Settings\SettingService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind SettingService as singleton so the same instance is reused
        $this->app->singleton(SettingService::class, function () {
            return new SettingService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── RBAC: super_admin bypasses ALL permission checks ─────────────
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
        });

        // ── Track last login timestamp ───────────────────────────────────
        Event::listen(Login::class, function (Login $event) {
            /** @var User $user */
            $user = $event->user;
            $user->timestamps = false;
            $user->update(['last_login_at' => now()]);
            $user->timestamps = true;
        });

        // Override filesystem config if R2 is enabled in DB settings
        try {
            // Force Livewire to use local disk for temp uploads to prevent CORS/R2 issues
            Config::set('livewire.temporary_file_upload.disk', 'local');

            if (Schema::hasTable('site_settings')) {
                if (setting('r2_storage.r2_enabled', false)) {
                    Config::set('filesystems.default', 'r2');
                    Config::set('filesystems.disks.r2.key',      setting('r2_storage.r2_access_key_id', config('filesystems.disks.r2.key')));
                    Config::set('filesystems.disks.r2.secret',   setting('r2_storage.r2_secret_access_key', config('filesystems.disks.r2.secret')));
                    Config::set('filesystems.disks.r2.bucket',   setting('r2_storage.r2_bucket', config('filesystems.disks.r2.bucket')));
                    Config::set('filesystems.disks.r2.url',      setting('r2_storage.r2_public_url', config('filesystems.disks.r2.url')));
                    Config::set('filesystems.disks.r2.endpoint', setting('r2_storage.r2_endpoint', config('filesystems.disks.r2.endpoint')));
                    Config::set('filesystems.disks.r2.use_path_style_endpoint', true);
                    Config::set('filesystems.disks.r2.throw', true);

                    // Force Filament to use r2 disk for uploads when R2 is enabled
                    Config::set('filament.default_filesystem_disk', 'r2');
                }

                // Override mail config
                if ($mailer = setting('mail.mail_mailer')) {
                    Config::set('mail.default', $mailer);
                    Config::set("mail.mailers.{$mailer}.transport", $mailer == 'testmail' ? 'smtp' : $mailer);
                    Config::set("mail.mailers.{$mailer}.host", setting('mail.mail_host'));
                    Config::set("mail.mailers.{$mailer}.port", setting('mail.mail_port'));
                    Config::set("mail.mailers.{$mailer}.encryption", setting('mail.mail_encryption'));
                    Config::set("mail.mailers.{$mailer}.username", setting('mail.mail_username'));
                    Config::set("mail.mailers.{$mailer}.password", setting('mail.mail_password'));
                    Config::set('mail.from.address', setting('mail.mail_from_address', config('mail.from.address')));
                    Config::set('mail.from.name', setting('mail.mail_from_name', config('mail.from.name')));
                }
            }
        } catch (\Throwable $e) {
            // DB chưa sẵn sàng (fresh install) — bỏ qua
        }
    }
}
