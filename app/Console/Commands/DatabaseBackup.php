<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class DatabaseBackup extends Command
{
    protected $signature = 'eiho:backup';

    protected $description = 'Create a SQL backup of the database';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $filename = 'production_'.date('Y-m-d_H-i-s').'.sql';
        $path = $dir.'/'.$filename;

        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();

        $fp = fopen($path, 'w');
        fwrite($fp, "-- Eiho One Database Backup\n");
        fwrite($fp, '-- Date: '.date('Y-m-d H:i:s')."\n");
        fwrite($fp, "-- Database: {$dbName}\n\n");

        foreach ($tables as $table) {
            $tableName = $table->{"Tables_in_{$dbName}"} ?? reset((array) $table);

            $create = DB::select("SHOW CREATE TABLE `{$tableName}`");
            if (! empty($create)) {
                fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                $createSql = $create[0]->{'Create Table'} ?? '';
                fwrite($fp, $createSql.";\n\n");
            }

            $rows = DB::table($tableName)->get();
            foreach ($rows as $row) {
                $values = array_map(function ($v) {
                    if (is_null($v)) {
                        return 'NULL';
                    }
                    if (is_bool($v)) {
                        return $v ? '1' : '0';
                    }

                    return "'".addslashes((string) $v)."'";
                }, (array) $row);

                $columns = array_keys((array) $row);
                $columnList = '`'.implode('`, `', $columns).'`';

                fwrite($fp, "INSERT INTO `{$tableName}` ({$columnList}) VALUES (".implode(', ', $values).");\n");
            }

            if ($rows->count() > 0) {
                fwrite($fp, "\n");
            }
        }

        fclose($fp);

        $size = filesize($path);
        $sizeMB = round($size / 1024 / 1024, 2);

        $this->info("Backup created: {$filename} ({$sizeMB} MB)");
        $this->info("Path: {$path}");

        // Cleanup old backups (keep last 5)
        $backups = collect(File::files($dir))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.sql'))
            ->sortByDesc(fn ($f) => $f->getMTime());

        if ($backups->count() > 5) {
            $toDelete = $backups->skip(5);
            foreach ($toDelete as $old) {
                File::delete($old->getPathname());
                $this->line('  Cleaned: '.$old->getFilename());
            }
        }

        return 0;
    }
}
