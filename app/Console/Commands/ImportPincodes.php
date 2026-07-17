<?php

namespace App\Console\Commands;

use App\Models\Pincode;
use Illuminate\Console\Command;

class ImportPincodes extends Command
{
    protected $signature = 'pincodes:import
        {--file= : Path to CSV file}
        {--url=https://github.com/dropdevrahul/pincodes-india/raw/main/pincode.csv : URL to download CSV from}
        {--truncate : Truncate table before import}';

    protected $description = 'Import India Post pincode master data from CSV';

    private const COLUMN_ALIASES = [
        'pincode' => ['Pincode', 'pincode', 'pin', 'zip'],
        'office_name' => ['OfficeName', 'office_name', 'officename', 'OfficeName', 'locality', 'name'],
        'district' => ['District', 'district', 'districtname', 'dist'],
        'state' => ['StateName', 'state', 'statename', 'state_name'],
        'latitude' => ['Latitude', 'latitude', 'lat'],
        'longitude' => ['Longitude', 'longitude', 'lng', 'long'],
    ];

    public function handle(): int
    {
        if ($this->option('truncate')) {
            Pincode::truncate();
            $this->info('Table truncated.');
        }

        $file = $this->option('file');
        if (! $file) {
            $file = tempnam(sys_get_temp_dir(), 'pincodes_') . '.csv';
            $url = $this->option('url');
            $this->info("Downloading from: {$url}");
            $contents = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 60, 'user_agent' => 'HolaApp/1.0'],
            ]));
            if ($contents === false) {
                $this->error('Failed to download CSV. Provide a file with --file or a valid --url.');
                return Command::FAILURE;
            }
            file_put_contents($file, $contents);
            $this->info('Downloaded ' . number_format(strlen($contents)) . ' bytes.');
        }

        if (! file_exists($file) || ! is_readable($file)) {
            $this->error('File not found or not readable: ' . $file);
            return Command::FAILURE;
        }

        $handle = fopen($file, 'r');
        if (! $handle) {
            $this->error('Cannot open file.');
            return Command::FAILURE;
        }

        $headers = fgetcsv($handle);
        if (! $headers) {
            $this->error('Empty or invalid CSV.');
            fclose($handle);
            return Command::FAILURE;
        }

        $headers = array_map('trim', $headers);
        $headerMap = [];
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            $found = false;
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $headers);
                if ($idx !== false) {
                    $headerMap[$field] = $idx;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $this->warn("Column for '{$field}' not found. Available: " . implode(', ', $headers));
            }
        }

        if (! isset($headerMap['pincode'], $headerMap['district'], $headerMap['state'])) {
            $this->error('CSV must contain at least: pincode, district, state columns.');
            $this->error('Found columns: ' . implode(', ', $headers));
            fclose($handle);
            return Command::FAILURE;
        }

        $batch = [];
        $count = 0;
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            $pincode = trim($row[$headerMap['pincode']] ?? '');
            if (strlen($pincode) !== 6 || ! ctype_digit($pincode)) {
                continue;
            }

            $latitude = isset($headerMap['latitude']) ? trim($row[$headerMap['latitude']] ?? '') : '';
            $longitude = isset($headerMap['longitude']) ? trim($row[$headerMap['longitude']] ?? '') : '';

            $batch[] = [
                'pincode' => $pincode,
                'locality' => trim($row[$headerMap['office_name'] ?? -1] ?? ''),
                'district' => trim($row[$headerMap['district']] ?? ''),
                'state' => trim($row[$headerMap['state']] ?? ''),
                'latitude' => is_numeric($latitude) && $latitude >= 6 && $latitude <= 37 ? (float) $latitude : null,
                'longitude' => is_numeric($longitude) && $longitude >= 68 && $longitude <= 98 ? (float) $longitude : null,
                'serviceable' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $count++;

            if (count($batch) >= $batchSize) {
                Pincode::insert($batch);
                $batch = [];
                $this->output->write("\r{$count} rows imported...");
            }
        }

        if (! empty($batch)) {
            Pincode::insert($batch);
        }

        fclose($handle);

        $this->newLine();
        $this->info("Done. {$count} pincodes imported.");

        return Command::SUCCESS;
    }
}
