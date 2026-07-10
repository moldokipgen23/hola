<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Setting;
use App\Models\NotificationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class NotifyUnclaimedBusinesses extends Command
{
    protected $signature = 'business:notify-unclaimed {--days=3} {--limit=50} {--dry-run}';
    protected $description = 'Notify unclaimed businesses via free channels (Email + Telegram + WhatsApp CallMeBot)';

    public function handle(): int
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $channel = Setting::get('notify_preferred_channel', 'email');
        $notEmail = Setting::get('notify_email', '1') === '1';
        $notTelegram = Setting::get('notify_telegram', '0') === '1';
        $notWhatsApp = Setting::get('notify_whatsapp', '0') === '1';

        // Find businesses that were imported X+ days ago and haven't been claimed
        $businesses = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->where('created_at', '<=', now()->subDays($days))
            ->whereDoesntHave('notificationLogs', function ($q) {
                $q->where('type', 'claim_invitation');
            })
            ->limit($limit)
            ->get();

        if ($businesses->isEmpty()) {
            $this->info("No unclaimed businesses to notify.");
            return 0;
        }

        $this->info("📧 Found {$businesses->count()} unclaimed businesses ({$days}+ days old)");
        $this->info("   Channel: {$channel} | Email: " . ($notEmail ? 'ON' : 'OFF') . " | Telegram: " . ($notTelegram ? 'ON' : 'OFF') . " | WhatsApp: " . ($notWhatsApp ? 'ON' : 'OFF'));

        $notified = 0;
        $failed = 0;

        foreach ($businesses as $business) {
            $this->info("  → {$business->name}");

            if ($dryRun) {
                $this->info("    [DRY RUN] Would notify via {$channel}");
                $notified++;
                continue;
            }

            $claimUrl = "https://hola.ehlom.com/claim/{$business->slug}";
            $message = $this->buildClaimMessage($business, $claimUrl);
            $sent = false;

            // Try channels in order of preference
            if ($channel === 'all' || $channel === 'email') {
                if ($notEmail && $business->email) {
                    $sent = $this->sendEmail($business->email, 'Your business is on Hola - Claim it now!', $message);
                }
            }

            if (!$sent && ($channel === 'all' || $channel === 'telegram')) {
                if ($notTelegram) {
                    $sent = $this->sendTelegram($message);
                }
            }

            if (!$sent && ($channel === 'all' || $channel === 'whatsapp')) {
                if ($notWhatsApp && $business->phone) {
                    $sent = $this->sendWhatsAppCallMeBot($business->phone, $message);
                }
            }

            // Log the attempt (even if failed, to avoid re-notifying)
            try {
                NotificationLog::create([
                    'business_id' => $business->id,
                    'type' => 'claim_invitation',
                    'channel' => $sent ? $channel : 'none',
                    'recipient' => $business->email ?? $business->phone ?? 'unknown',
                    'message' => $message,
                    'status' => $sent ? 'sent' : 'failed',
                    'sent_at' => $sent ? now() : null,
                ]);
            } catch (\Exception $e) { /* log failure should not block */ }

            if ($sent) {
                $notified++;
                $this->info("    ✅ Sent via {$channel}");
            } else {
                $failed++;
                $this->warn("    ❌ Failed (no channel configured or no contact info)");
            }

            usleep(500000);
        }

        $this->info("");
        $this->info("📊 Notification complete:");
        $this->info("  Sent: {$notified}");
        $this->info("  Failed: {$failed}");

        return 0;
    }

    private function buildClaimMessage(Business $business, string $claimUrl): string
    {
        $name = $business->name;

        return "Hi! Your business \"{$name}\" is listed on Hola - Churachandpur's #1 business directory.\n\n" .
            "Claim your listing for FREE to:\n" .
            "- Update your business info\n" .
            "- Add photos & products\n" .
            "- Respond to reviews\n" .
            "- Get found by more customers\n\n" .
            "Claim now: {$claimUrl}\n\n" .
            "Questions? Reply to this message.";
    }

    private function sendEmail(string $to, string $subject, string $body): bool
    {
        try {
            $fromAddress = Setting::get('smtp_from_address', config('mail.from.address'));
            $fromName = Setting::get('smtp_from_name', config('mail.from.name', 'Hola'));

            if (!$fromAddress) return false;

            \Illuminate\Support\Facades\Mail::raw(
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

            if (!$token || !$chatId) return false;

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
            if (!$apiKey) return false;

            $to = $this->normalizePhone($phone);

            $response = Http::get("https://api.callmebot.com/whatsapp.php", [
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
        if (!str_starts_with($phone, '+')) {
            if (strlen($phone) === 10) {
                $phone = '+91' . $phone;
            }
        }
        return $phone;
    }
}
