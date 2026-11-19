<?php

namespace App\Console\Commands;

use App\Models\EligibleVoter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportEligibleVoters extends Command
{
    protected $signature = 'voting:import-csv {path}';
    protected $description = 'Import eligible voters from a CSV file containing a kode_unik column';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! file_exists($path) || ! is_readable($path)) {
            $this->error('File not found or not readable: '.$path);
            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $header = array_map('strtolower', array_map('trim', $header));
        $kodeIndex = array_search('kode_unik', $header, true);
        if ($kodeIndex === false) {
            $this->error('The CSV must contain a "kode_unik" column.');
            fclose($handle);
            return self::FAILURE;
        }

        $existingCodes = EligibleVoter::pluck('kode_unik')->filter()->flip();
        $imported = 0;
        $duplicateExisting = 0;
        $duplicateInFile = 0;
        $invalid = 0;
        $seen = [];
        $toInsert = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (! is_array($row) || count($row) <= $kodeIndex) {
                $invalid++;
                continue;
            }
            $kode = trim((string) ($row[$kodeIndex] ?? ''));
            if (! preg_match('/^[1-9]\d{4}$/', $kode)) {
                $invalid++;
                continue;
            }
            if (isset($seen[$kode])) {
                $duplicateInFile++;
                continue;
            }
            $seen[$kode] = true;
            if ($existingCodes->has($kode)) {
                $duplicateExisting++;
                continue;
            }
            $toInsert[] = [
                'nik' => null,
                'kode_unik' => $kode,
                'name' => null,
                'department' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        fclose($handle);

        if (! empty($toInsert)) {
            DB::transaction(function () use ($toInsert, &$imported) {
                foreach (array_chunk($toInsert, 100) as $chunk) {
                    EligibleVoter::insert($chunk);
                    $imported += count($chunk);
                }
            });
        }

        $this->info("Imported: {$imported}");
        $this->info("Existing duplicates skipped: {$duplicateExisting}");
        $this->info("Duplicate rows in file skipped: {$duplicateInFile}");
        $this->info("Invalid rows skipped: {$invalid}");

        return self::SUCCESS;
    }
}
