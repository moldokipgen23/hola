<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\Setting;
use App\Services\ClaimNotificationService;
use Illuminate\Console\Command;

/**
 * Auto-pilot for claim notifications.
 *
 * Runs on a schedule and sends to unclaimed imported businesses:
 *   1. First invitation after N days of import.
 *   2. Follow-up reminders to businesses still unclaimed after the reminder
 *      interval, up to a max number of reminders.
 *
 * SAFETY: this command does NOTHING unless the `autopilot_claim_enabled`
 * setting is "1". Nothing is sent until the operator turns the switch on.
 */
class AutoPilotClaimNotifications extends Command
{
    protected $signature = 'autopilot:claim-notifications {--dry-run}';

    protected $description = 'Automatically invite unclaimed businesses to claim their listing, with follow-up reminders (runs only when enabled)';

    public function handle(): int
    {
        $enabled = Setting::get('autopilot_claim_enabled', '0') === '1';
        if (! $enabled) {
            $this->info('Auto-pilot claim notifications are DISABLED. Nothing sent. Enable in Settings → Notifications.');

            return 0;
        }

        $dryRun = $this->option('dry-run');
        $batch = max(1, (int) Setting::get('autopilot_claim_batch', 20));
        $channel = Setting::get('autopilot_claim_channel', 'whatsapp_meta');
        $firstAfterDays = (int) Setting::get('autopilot_first_after_days', 1);
        $reminderAfterDays = (int) Setting::get('autopilot_reminder_after_days', 7);
        $maxReminders = (int) Setting::get('autopilot_max_reminders', 2);

        $service = app(ClaimNotificationService::class);
        $template = Setting::get('template_claim_sms', 'Hi {business_name}! Your business is now on {site_name} — claim it for free. {claim_url}');

        $service->configuredChannels();

        // Candidate businesses: unclaimed imports with a phone, never notified yet,
        // and old enough to be invited.
        $firstBatch = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->whereDoesntHave('notificationLogs', fn ($q) => $q->where('type', 'claim_invitation'))
            ->where('created_at', '<=', now()->subDays($firstAfterDays))
            ->limit($batch)
            ->get();

        $this->info("First invitations target: {$firstBatch->count()}");

        $sent = 0;
        $failed = 0;
        foreach ($firstBatch as $business) {
            $message = $service->renderTemplate($template, $business);
            if ($dryRun) {
                $this->line("  [DRY RUN] would notify {$business->name} via {$channel}");
                $sent++;

                continue;
            }
            $result = $service->send($business, $message, $channel);
            if ($result['sent']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        // Follow-up reminders: businesses already invited (have a claim_invitation
        // log) but still unclaimed, and whose LAST invitation is older than the
        // reminder interval, and who have received fewer than max reminders.
        $reminderTargets = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->whereNotNull('phone')->where('phone', '!=', '')
            ->whereHas('notificationLogs', function ($q) use ($reminderAfterDays) {
                $q->where('type', 'claim_invitation')
                    ->where('status', 'sent')
                    ->where('sent_at', '<=', now()->subDays($reminderAfterDays));
            })
            ->where(function ($q) use ($maxReminders) {
                $q->whereRaw(
                    '(SELECT COUNT(*) FROM notification_logs nl WHERE nl.business_id = businesses.id AND nl.type = ? AND nl.status = ?) < ?',
                    ['claim_invitation', 'sent', $maxReminders]
                );
            })
            ->limit($batch)
            ->get();

        $this->info("Follow-up reminders target: {$reminderTargets->count()}");

        $reminderTemplate = Setting::get('template_claim_reminder', 'Hi {business_name}! Just a reminder — your business is listed on {site_name} and you can claim it for free. Claim now: {claim_url}');

        $reminded = 0;
        $reminderFailed = 0;
        foreach ($reminderTargets as $business) {
            $message = $service->renderTemplate($reminderTemplate, $business);
            if ($dryRun) {
                $this->line("  [DRY RUN] would remind {$business->name} via {$channel}");
                $reminded++;

                continue;
            }
            $result = $service->send($business, $message, $channel);
            if ($result['sent']) {
                $reminded++;
            } else {
                $reminderFailed++;
            }
        }

        $this->info("Done. First invitations sent: {$sent} (failed {$failed}) | Reminders sent: {$reminded} (failed {$reminderFailed})");

        return 0;
    }
}
