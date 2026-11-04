<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\MasterHsCode;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BulkImportMasterHsCodes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'master-hs-codes:bulk-import
                            {file : Path to CSV file containing tariff codes}
                            {--chunk=1000 : Number of records per atomic batch upsert}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'High-performance streaming bulk importer for 10,000+ Pakistan Customs Tariff (PCT) and HS codes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $chunkSize = (int) $this->option('chunk');

        if (! file_exists($filePath) || ! is_readable($filePath)) {
            $this->error("The specified file does not exist or is not readable: {$filePath}");
            return Command::FAILURE;
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            $this->error("Failed to open file: {$filePath}");
            return Command::FAILURE;
        }

        $headerRow = fgetcsv($handle);
        if (! $headerRow) {
            $this->error("CSV file appears to be empty or missing header row.");
            fclose($handle);
            return Command::FAILURE;
        }

        // Normalize header keys
        $header = array_map(function ($col) {
            $cleaned = strtolower(trim(str_replace(['"', "'", "\xEF\xBB\xBF"], '', $col)));
            return match ($cleaned) {
                'pct_code', 'pct', 'code' => 'hs_code',
                'desc', 'item_description', 'title' => 'description',
                'rate', 'tax_rate', 'rate_bps' => 'default_rate_basis_points',
                default => $cleaned,
            };
        }, $headerRow);

        $hsCodeIdx = array_search('hs_code', $header);
        $descIdx = array_search('description', $header);

        if ($hsCodeIdx === false || $descIdx === false) {
            $this->error("CSV must contain at least 'hs_code' (or 'pct_code') and 'description' columns.");
            fclose($handle);
            return Command::FAILURE;
        }

        $taxCatIdx = array_search('tax_category', $header);
        $rateIdx = array_search('default_rate_basis_points', $header);
        $authIdx = array_search('authority', $header);

        $this->info("Starting streaming bulk import from: {$filePath}");
        $this->info("Batch chunk size: {$chunkSize} records per query");

        $chunk = [];
        $totalProcessed = 0;
        $now = now();
        $startTime = microtime(true);

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                continue;
            }

            $rawHsCode = trim($row[$hsCodeIdx] ?? '');
            $rawDesc = trim($row[$descIdx] ?? '');

            if (empty($rawHsCode) || empty($rawDesc)) {
                continue;
            }

            $taxCategory = ($taxCatIdx !== false && ! empty($row[$taxCatIdx]))
                ? trim($row[$taxCatIdx])
                : 'general_goods';

            $rateBasisPoints = ($rateIdx !== false && is_numeric(trim($row[$rateIdx])))
                ? (int) trim($row[$rateIdx])
                : 1800;

            $authority = ($authIdx !== false && ! empty($row[$authIdx]))
                ? strtolower(trim($row[$authIdx]))
                : 'fbr';

            $chunk[] = [
                'id' => (string) Str::uuid(),
                'hs_code' => $rawHsCode,
                'description' => $rawDesc,
                'tax_category' => $taxCategory,
                'default_rate_basis_points' => $rateBasisPoints,
                'authority' => $authority,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($chunk) >= $chunkSize) {
                MasterHsCode::upsert(
                    $chunk,
                    ['hs_code'],
                    ['description', 'tax_category', 'default_rate_basis_points', 'authority', 'is_active', 'updated_at']
                );
                $totalProcessed += count($chunk);
                $this->output->write("<comment>.</comment>");
                $chunk = [];
            }
        }

        // Flush remaining records
        if (! empty($chunk)) {
            MasterHsCode::upsert(
                $chunk,
                ['hs_code'],
                ['description', 'tax_category', 'default_rate_basis_points', 'authority', 'is_active', 'updated_at']
            );
            $totalProcessed += count($chunk);
        }

        fclose($handle);

        $duration = round(microtime(true) - $startTime, 2);
        $this->newLine();
        $this->info("Successfully imported / synced {$totalProcessed} tariff codes in {$duration} seconds!");

        AuditLog::create([
            'tenant_id' => null,
            'user_id' => null,
            'event' => 'hs_codes_cli_bulk_imported',
            'auditable_type' => MasterHsCode::class,
            'auditable_id' => (string) Str::uuid(),
            'new_values' => [
                'file' => basename($filePath),
                'imported_records_count' => $totalProcessed,
                'duration_seconds' => $duration,
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Artisan CLI',
        ]);

        return Command::SUCCESS;
    }
}

