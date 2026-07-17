<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\NotificationLog;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class NotifyUnclaimedBusinesses extends Command
{
    protected $signature = 'business:notify-unclaimed {--limit=50} {--dry-run}';

    protected $description = 'Notify unclaimed businesses via free channels (Email + Telegram + WhatsApp CallMeBot)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        // Global fallback settings
        $globalChannel = Setting::get('notify_preferred_channel', 'email');
        $globalEmail = Setting::get('notify_email', '1') === '1';
        $globalTelegram = Setting::get('notify_telegram', '0') === '1';
        $globalWhatsApp = Setting::get('notify_whatsapp', '0') === '1';

        // Find businesses that were imported and haven't been claimed
        // Filter by per-business settings
        $businesses = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->where('claim_notifications_enabled', true)
            ->whereDoesntHave('notificationLogs', function ($q) {
                $q->where('type', 'claim_invitation');
            })
            ->where(function ($q) {
                // Business-specific delay (fallback to 3 days)
                $q->whereRaw('created_at <= DATE_SUB(NOW(), INTERVAL COALESCE(claim_notification_delay_days, 3) DAY)');
            })
            ->limit($limit)
            ->get();

        if ($businesses->isEmpty()) {
            $this->info('No unclaimed businesses to notify.');

            return 0;
        }

        $this->info("📧 Found {$businesses->count()} unclaimed businesses ready for notification");

        $notified = 0;
        $failed = 0;

        foreach ($businesses as $business) {
            $this->info("  → {$business->name} (delay: {$business->claim_notification_delay_days}d, channel: {$business->claim_preferred_channel})");

            if ($dryRun) {
                $this->info("    [DRY RUN] Would notify via {$business->claim_preferred_channel}");
                $notified++;

                continue;
            }

            $claimUrl = "https://hola.ehlom.com/claim/{$business->slug}";
            $message = $this->buildClaimMessage($business, $claimUrl);
            $sent = false;
            $usedChannel = 'none';

            // Determine effective channel per business
            $effectiveChannel = $business->claim_preferred_channel;
            if ($effectiveChannel === 'all') {
                $effectiveChannel = $globalChannel; // fallback to global
            }

            // Try channels in order of business preference
            $channels = $this->getChannelOrder($effectiveChannel);

            foreach ($channels as $channel) {
                if ($sent) {
                    break;
                }

                // Check if channel is enabled globally
                $enabled = match ($channel) {
                    'email' => $globalEmail,
                    'telegram' => $globalTelegram,
                    'whatsapp' => $globalWhatsApp,
                    default => false,
                };

                if (! $enabled) {
                    continue;
                }

                switch ($channel) {
                    case 'email':
                        if ($business->email) {
                            $subject = $this->buildEmailSubject($business);
                            $sent = $this->sendEmail($business->email, $subject, $message);
                            if ($sent) {
                                $usedChannel = 'email';
                            }
                        }
                        break;
                    case 'telegram':
                        $sent = $this->sendTelegram($message);
                        if ($sent) {
                            $usedChannel = 'telegram';
                        }
                        break;
                    case 'whatsapp':
                        if ($business->phone) {
                            $sent = $this->sendWhatsAppCallMeBot($business->phone, $message);
                            if ($sent) {
                                $usedChannel = 'whatsapp';
                            }
                        }
                        break;
                }
            }

            // Log the attempt
            try {
                NotificationLog::create([
                    'business_id' => $business->id,
                    'type' => 'claim_invitation',
                    'channel' => $usedChannel,
                    'recipient' => $business->email ?? $business->phone ?? 'unknown',
                    'message' => $message,
                    'status' => $sent ? 'sent' : 'failed',
                    'sent_at' => $sent ? now() : null,
                ]);
            } catch (\Exception $e) { /* log failure should not block */
            }

            if ($sent) {
                $notified++;
                $this->info("    ✅ Sent via {$usedChannel}");
            } else {
                $failed++;
                $this->warn('    ❌ Failed (no channel enabled or no contact info)');
            }

            usleep(500000);
        }

        $this->info('');
        $this->info('📊 Notification complete:');
        $this->info("  Sent: {$notified}");
        $this->info("  Failed: {$failed}");

        return 0;
    }

    private function getChannelOrder(string $preferred): array
    {
        // If preferred is specific channel, try that first, then fall back to others
        if (in_array($preferred, ['email', 'telegram', 'whatsapp'])) {
            return array_merge([$preferred], array_diff(['email', 'telegram', 'whatsapp'], [$preferred]));
        }

        // 'all' or unknown -> use global preference order
        return ['email', 'telegram', 'whatsapp'];
    }

    private function buildClaimMessage(Business $business, string $claimUrl): string
    {
        $template = Setting::get('template_claim_sms', null);
        $siteName = Setting::get('site_name', 'Hola');
        $district = Setting::get('district', 'Churachandpur');

        if ($template) {
            return str_replace(
                ['{business_name}', '{claim_url}', '{site_name}', '{district}', '{address}', '{phone}'],
                [$business->name, $claimUrl, $siteName, $district, $business->address ?? '', $business->phone ?? ''],
                $template
            );
        }

        return "Hi! Your business \"{$business->name}\" is listed on {$siteName} - {$district}'s #1 business directory.\n\n".
            "Claim your listing for FREE to:\n".
            "- Update your business info\n".
            "- Add photos & products\n".
            "- Respond to reviews\n".
            "- Get found by more customers\n\n".
            "Claim now: {$claimUrl}\n\n".
            'Questions? Reply to this message.';
    }

    private function buildEmailSubject(Business $business): string
    {
        $template = Setting::get('template_claim_subject', null);
        $siteName = Setting::get('site_name', 'Hola');
        $district = Setting::get('district', 'Churachandpur');

        if ($template) {
            return str_replace(
                ['{business_name}', '{site_name}', '{district}'],
                [$business->name, $siteName, $district],
                $template
            );
        }

        return "Your business is on {$siteName} - Claim it now!";
    }

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $fromAddress = Setting::get('smtp_from_address', config('mail.from.address'));
            $fromName = Setting::get('smtp_from_name', config('mail.from.name', 'Hola'));

            if (! $fromAddress) {
                return false;
            }

            Mail::raw(
                $body,
                function ($message) use ($to, $subject, $fromAddress, $fromName) {
                    $message->to($to)
                        ->subject($subject)
                        ->from($fromAddress, $fromName);
                }
            );

            return true;
        } catch (\Exception $e) {
            $this->warn("    Email failed: {$e->getMessage()}");

            return false;
        }
    }

    private function sendTelegram(string $message): bool
    {
        try {
            $token = Setting::get('telegram_bot_token');
            $chatId = Setting::get('telegram_chat_id');

            if (! $token || ! $chatId) {
                return false;
            }

            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            $this->warn("    Telegram failed: {$e->getMessage()}");

            return false;
        }
    }

    private function sendWhatsAppCallMeBot(string $phone, string $message): bool
    {
        try {
            $apiKey = Setting::get('callmebot_api_key');
            if (! $apiKey) {
                return false;
            }

            $to = $this->normalizePhone($phone);

            $response = Http::get('https://api.callmebot.com/whatsapp.php', [
                'phone' => $to,
                'text' => $message,
                'apikey' => $apiKey,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            $this->warn("    WhatsApp failed: {$e->getMessage()}");

            return false;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (! str_starts_with($phone, '+')) {
            if (strlen($phone) === 10) {
                $phone = '+91'.$phone;
            }
        }

        return $phone;
    }
}
