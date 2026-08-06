<?php

namespace App\Listeners;

use App\Models\Notification;
use App\Models\User;
use App\Models\VendorNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendQueueFailureAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;

    public function __construct() {}

    public function handle(object $event): void
    {
        if (! property_exists($event, 'job') || ! property_exists($event, 'exception')) {
            return;
        }

        $job = $event->job;
        $exception = $event->exception;

        $jobClass = get_class($job->resolve());
        $queue = $job->getQueue();
        $connection = $job->getConnectionName();
        $exceptionMessage = $exception->getMessage();
        $failedAt = now()->toDateTimeString();

        Log::error('Queue job failed', [
            'job_class' => $jobClass,
            'queue' => $queue,
            'connection' => $connection,
            'exception' => $exceptionMessage,
            'attempts' => $job->attempts(),
            'failed_at' => $failedAt,
        ]);

        $this->createVendorNotification($jobClass, $queue, $exceptionMessage);
        $this->createAdminNotification($jobClass, $queue, $exceptionMessage, $failedAt);
    }

    protected function createVendorNotification(string $jobClass, string $queue, string $exceptionMessage): void
    {
        $businessId = $this->extractBusinessId($jobClass);

        if (! $businessId) {
            return;
        }

        VendorNotification::create([
            'business_id' => $businessId,
            'type' => 'queue_failure',
            'title' => 'System Task Failed',
            'body' => 'A background task has failed and may affect your business. Our team has been notified.',
            'data' => [
                'job_class' => $jobClass,
                'queue' => $queue,
                'error' => Str::limit($exceptionMessage, 500),
            ],
            'is_read' => false,
        ]);
    }

    protected function createAdminNotification(string $jobClass, string $queue, string $exceptionMessage, string $failedAt): void
    {
        $admins = User::whereIn('role', ['super_admin', 'admin'])->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'queue_failure',
                'title' => 'Queue Job Failed',
                'body' => "Job `{$jobClass}` failed on queue `{$queue}`. {$exceptionMessage}",
                'data' => [
                    'job_class' => $jobClass,
                    'queue' => $queue,
                    'exception' => Str::limit($exceptionMessage, 1000),
                    'failed_at' => $failedAt,
                ],
                'is_read' => false,
            ]);
        }
    }

    protected function extractBusinessId(string $jobClass): ?int
    {
        $patterns = [
            '/business[_-]?id["\s:=]+(\d+)/i',
            '/Business::find\((\d+)\)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $jobClass, $matches)) {
                return (int) $matches[1];
            }
        }

        return null;
    }
}
