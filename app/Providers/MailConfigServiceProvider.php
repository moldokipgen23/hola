<?php

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (!$this->app->runningInConsole() && !DB::getSchemaBuilder()->hasTable('settings')) {
            return;
        }

        try {
            $smtpSettings = $this->getSmtpSettings();

            if (!empty($smtpSettings['smtp_driver'])) {
                Config::set('mail.default', $smtpSettings['smtp_driver']);
            }

            if ($smtpSettings['smtp_driver'] === 'smtp') {
                Config::set('mail.mailers.smtp.transport', 'smtp');
                Config::set('mail.mailers.smtp.host', $smtpSettings['smtp_host'] ?? '127.0.0.1');
                Config::set('mail.mailers.smtp.port', (int) ($smtpSettings['smtp_port'] ?? 587));
                Config::set('mail.mailers.smtp.encryption', $smtpSettings['smtp_encryption'] ?? 'tls');
                Config::set('mail.mailers.smtp.username', $smtpSettings['smtp_username'] ?? null);
                Config::set('mail.mailers.smtp.password', $smtpSettings['smtp_password'] ?? null);
                Config::set('mail.mailers.smtp.local_domain', $smtpSettings['smtp_local_domain'] ?? null);
            }

            if (!empty($smtpSettings['smtp_from_address'])) {
                Config::set('mail.from.address', $smtpSettings['smtp_from_address']);
            }
            if (!empty($smtpSettings['smtp_from_name'])) {
                Config::set('mail.from.name', $smtpSettings['smtp_from_name']);
            }
        } catch (\Exception $e) {
            // Table might not exist yet during migrations
        }
    }

    private function getSmtpSettings(): array
    {
        try {
            return DB::table('settings')
                ->where('group', 'smtp')
                ->pluck('value', 'key')
                ->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }
}
