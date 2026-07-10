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
    protected $description = 'Notify unclaimed businesses via email/SMS to claim their listing on Hola';

    public function handle(): int
    {
        $days = $this->option('days');
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        // Find businesses that were imported 3+ days ago and haven't been claimed
        $businesses = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->whereNotNull('phone')
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

        $this->info("📧 Found {$businesses->count()} unclaimed businesses to notify ({$days}+ days old)");

        $notified = 0;
        $failed = 0;

        foreach ($businesses as $business) {
            $this->info("  → {$business->name} ({$business->phone})");

            if ($dryRun) {
                $this->info("    [DRY RUN] Would send claim invitation");
                $notified++;
                continue;
            }

            try {
                // Build the claim message
                $message = $this->buildClaimMessage($business);

                // Try WhatsApp first (if available), then SMS
                $sent = false;

                // Try WhatsApp via Twilio
                $sent = $this->sendWhatsApp($business->phone, $message);

                // Fallback to SMS
                if (!$sent) {
                    $sent = $this->sendSMS($business->phone, $message);
                }

                if ($sent) {
                    NotificationLog::create([
                        'business_id' => $business->id,
                        'type' => 'claim_invitation',
                        'channel' => 'sms',
                        'recipient' => $business->phone,
                        'message' => $message,
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);
                    $notified++;
                    $this->info("    ✅ Sent");
                } else {
                    NotificationLog::create([
                        'business_id' => $business->id,
                        'type' => 'claim_invitation',
                        'channel' => 'sms',
                        'recipient' => $business->phone,
                        'message' => $message,
                        'status' => 'failed',
                    ]);
                    $failed++;
                    $this->warn("    ❌ Failed to send");
                }

                // Rate limit
                usleep(500000);

            } catch (\Exception $e) {
                $failed++;
                $this->warn("    ❌ Error: {$e->getMessage()}");
            }
        }

        $this->info("");
        $this->info("📊 Notification complete:");
        $this->info("  Sent: {$notified}");
        $this->info("  Failed: {$failed}");

        return 0;
    }

    private function buildClaimMessage(Business $business): string
    {
        $name = $business->name;
        $claimUrl = "https://hola.ehlom.com/claim/{$business->slug}";

        return "Hi! Your business \"{$name}\" is listed on Hola - Churachandpur's #1 business directory. 🌟\n\n" .
            "Claim your listing for FREE to:\n" .
            "✅ Update your business info\n" .
            "✅ Add photos & products\n" .
            "✅ Respond to reviews\n" .
            "✅ Get found by more customers\n\n" .
            "Claim now: {$claimUrl}\n\n" .
            "Questions? Reply to this message.";
    }

    private function sendWhatsApp(string $phone, string $message): bool
    {
        try {
            $accountSid = config('services.twilio.sid');
            $authToken = config('services.twilio.token');
            $from = config('services.twilio.whatsapp_from');

            if (!$accountSid || !$authToken || !$from) {
                return false;
            }

            $to = 'whatsapp:' . $this->normalizePhone($phone);

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $message,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function sendSMS(string $phone, string $message): bool
    {
        try {
            $accountSid = config('services.twilio.sid');
            $authToken = config('services.twilio.token');
            $from = config('services.twilio.sms_from');

            if (!$accountSid || !$authToken || !$from) {
                $this->warn("    Twilio not configured, skipping SMS");
                return false;
            }

            $to = $this->normalizePhone($phone);

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Messages.json", [
                    'From' => $from,
                    'To' => $to,
                    'Body' => $message,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (!str_starts_with($phone, '+')) {
            // Assume India (+91) if 10 digits
            if (strlen($phone) === 10) {
                $phone = '+91' . $phone;
            }
        }
        return $phone;
    }
}
