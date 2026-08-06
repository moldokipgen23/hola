<?php

namespace App\Http\Controllers;

use App\Services\OperationalHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;

class HealthController extends Controller
{
    public function __invoke(OperationalHealthService $health): JsonResponse
    {
        $snapshot = $health->snapshot();
        $healthy = $snapshot['status'] === 'ok';

        $backupStatus = $this->getBackupStatus();
        $snapshot['checks']['backup'] = $backupStatus;

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $snapshot['checks'],
            'checked_at' => $snapshot['checked_at'],
        ], 200);
    }

    public function backupStatus(): JsonResponse
    {
        $status = $this->getBackupStatus();

        return response()->json([
            'status' => $status['status'],
            'backup' => $status,
            'checked_at' => now()->toDateTimeString(),
        ], $status['status'] === 'ok' ? 200 : 503);
    }

    protected function getBackupStatus(): array
    {
        $backupPaths = [
            storage_path('app/backups'),
            storage_path('app/backup'),
            database_path('backups'),
            base_path('backups'),
        ];

        $backupDir = null;
        foreach ($backupPaths as $path) {
            if (is_dir($path)) {
                $backupDir = $path;
                break;
            }
        }

        if (! $backupDir) {
            return [
                'status' => 'warning',
                'message' => 'No backup directory found',
                'latest_backup' => null,
                'backup_age_hours' => null,
                'backup_size_mb' => null,
            ];
        }

        $files = collect(File::files($backupDir))
            ->filter(fn ($file) => in_array($file->getExtension(), ['sql', 'gz', 'zip', 'sql.gz', 'sql.zip', 'bak']))
            ->sortByDesc(fn ($file) => $file->getMTime());

        if ($files->isEmpty()) {
            return [
                'status' => 'warning',
                'message' => 'No backup files found in '.$backupDir,
                'latest_backup' => null,
                'backup_age_hours' => null,
                'backup_size_mb' => null,
            ];
        }

        $latest = $files->first();
        $ageHours = round((time() - $latest->getMTime()) / 3600, 2);
        $sizeMB = round($latest->getSize() / (1024 * 1024), 2);

        $isRecent = $ageHours <= 24;

        return [
            'status' => $isRecent ? 'ok' : 'warning',
            'message' => $isRecent ? 'Backup is recent' : 'Backup is older than 24 hours',
            'latest_backup' => $latest->getFilename(),
            'backup_age_hours' => $ageHours,
            'backup_size_mb' => $sizeMB,
            'backup_path' => $latest->getPathname(),
            'backup_count' => $files->count(),
        ];
    }
}
