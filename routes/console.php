<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Government;
use App\Models\Location;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('osm:import-places {path : Absolute path to TSV/CSV file} {--wipe=soft : soft|hard} {--delimiter=\t : Field delimiter, default is tab} {--chunk=1000 : Batch insert size} {--dry-run : Parse but do not write to DB}', function () {
    $path = (string) $this->argument('path');
    $wipe = (string) $this->option('wipe');
    $delimiterOpt = (string) $this->option('delimiter');
    $chunkSize = (int) $this->option('chunk');
    $dryRun = (bool) $this->option('dry-run');

    $delimiterOpt = trim($delimiterOpt);
    $delimiter = $delimiterOpt === '\\t' ? "\t" : mb_substr($delimiterOpt, 0, 1);

    if ($delimiter === '') {
        $this->error('Invalid --delimiter value. Must be a single character (e.g. "," or "\\t").');
        return 1;
    }

    if (!is_file($path)) {
        $this->error('File not found: '.$path);
        return 1;
    }

    if (!in_array($wipe, ['soft', 'hard'], true)) {
        $this->error("Invalid --wipe value '{$wipe}'. Use soft or hard.");
        return 1;
    }

    if ($chunkSize < 1) {
        $this->error('Invalid --chunk value. Must be >= 1.');
        return 1;
    }

    $file = new SplFileObject($path);
    $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY);
    $file->setCsvControl($delimiter);

    $header = null;
    $rowNumber = 0;
    $skippedUnnamed = 0;
    $skippedInvalid = 0;
    $inserted = 0;
    $batch = [];
    $govCache = [];

    $this->info('Parsing file: '.$path);
    $this->info('Wipe mode: '.$wipe.($dryRun ? ' (dry-run)' : ''));

    DB::beginTransaction();
    try {
        if (!$dryRun) {
            if ($wipe === 'soft') {
                Location::query()->delete();
            } else {
                // Hard delete (destructive). This removes records (and cascades where FK cascade is defined).
                Location::query()->withTrashed()->forceDelete();
            }
        }

        foreach ($file as $row) {
            $rowNumber++;

            if ($rowNumber === 1) {
                if (!is_array($row)) {
                    $this->error('Invalid header row.');
                    DB::rollBack();
                    return 1;
                }

                $header = array_map(static fn ($v) => trim((string) $v), $row);
                continue;
            }

            if (!is_array($row) || $header === null) {
                $skippedInvalid++;
                continue;
            }

            // Some CSV parsers return [null] at EOF
            if (count($row) === 1 && ($row[0] === null || $row[0] === '')) {
                continue;
            }

            $assoc = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $assoc[$key] = array_key_exists($i, $row) ? trim((string) $row[$i]) : null;
            }

            $governorate = $assoc['governorate'] ?? $assoc['governorate_name'] ?? null;
            $name = $assoc['name'] ?? null;
            $lat = $assoc['lat'] ?? $assoc['latitude'] ?? null;
            $lon = $assoc['lon'] ?? $assoc['lng'] ?? $assoc['longitude'] ?? null;

            if ($name === null || $name === '' || $name === '(unnamed)') {
                $skippedUnnamed++;
                continue;
            }

            if ($governorate === null || $governorate === '') {
                $skippedInvalid++;
                continue;
            }

            if ($lat === null || $lat === '' || $lon === null || $lon === '') {
                $skippedInvalid++;
                continue;
            }

            $latF = (float) $lat;
            $lonF = (float) $lon;

            if (!isset($govCache[$governorate])) {
                if ($dryRun) {
                    $govCache[$governorate] = 0;
                } else {
                    $government = Government::query()->firstOrCreate(
                        ['accessible_locations' => $governorate],
                        ['accessible_locations' => $governorate]
                    );
                    $govCache[$governorate] = (int) $government->id;
                }
            }

            $governmentId = $govCache[$governorate];
            if (!$dryRun && $governmentId <= 0) {
                $skippedInvalid++;
                continue;
            }

            $now = now();
            $batch[] = [
                'government_id' => $dryRun ? 1 : $governmentId,
                'name' => $name,
                'address' => $governorate,
                'latitude' => $latF,
                'longitude' => $lonF,
                'average_rating' => 0,
                'reviews_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= $chunkSize) {
                if (!$dryRun) {
                    DB::table('locations')->insert($batch);
                }
                $inserted += count($batch);
                $batch = [];
            }
        }

        if (count($batch) > 0) {
            if (!$dryRun) {
                DB::table('locations')->insert($batch);
            }
            $inserted += count($batch);
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
        }
    } catch (Throwable $e) {
        DB::rollBack();
        $this->error('Import failed: '.$e->getMessage());
        return 1;
    }

    $this->info('Done.');
    $this->line('Inserted: '.$inserted);
    $this->line('Skipped (unnamed): '.$skippedUnnamed);
    $this->line('Skipped (invalid/missing fields): '.$skippedInvalid);

    return 0;
})->purpose('Import OSM places into locations table (skips (unnamed) rows)');
