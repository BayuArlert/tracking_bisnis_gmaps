<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class BackfillBusinessAreas extends Command
{
    protected $signature = 'businesses:backfill-areas {--dry-run : Do not update DB, only show summary} {--chunk=500 : Process size per chunk}';

    protected $description = 'Normalize and backfill area field from address for existing businesses';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = (int) $this->option('chunk');

        $this->info('Starting backfill'.($dryRun ? ' (dry-run)' : ''));

        $updated = 0; $skipped = 0; $total = 0;

        Business::select('id', 'area', 'address')
            ->orderBy('id')
            ->chunk($chunk, function($rows) use (&$updated, &$skipped, &$total, $dryRun) {
                foreach ($rows as $row) {
                    $total++;
                    $normalized = $this->extractAreaFromAddress($row->address ?? '');

                    if (empty($normalized)) {
                        $skipped++;
                        continue;
                    }

                    // Skip if already same value
                    if ($row->area === $normalized) {
                        $skipped++;
                        continue;
                    }

                    if (!$dryRun) {
                        DB::table('businesses')->where('id', $row->id)->update(['area' => $normalized]);
                    }
                    $updated++;
                }
            });

        $this->info("Processed: {$total}, Updated: {$updated}, Skipped: {$skipped}");
        return self::SUCCESS;
    }

    private function extractAreaFromAddress(string $address): ?string
    {
        $parts = array_map('trim', explode(',', $address));

        $areas = [
            'Denpasar' => 'Kota Denpasar',
            'Badung' => 'Kabupaten Badung',
            'Gianyar' => 'Kabupaten Gianyar',
            'Tabanan' => 'Kabupaten Tabanan',
            'Klungkung' => 'Kabupaten Klungkung',
            'Bangli' => 'Kabupaten Bangli',
            'Karangasem' => 'Kabupaten Karangasem',
            'Buleleng' => 'Kabupaten Buleleng',
            'Jembrana' => 'Kabupaten Jembrana',
        ];

        $patterns = [
            'kabupaten' => '/^(kabupaten\s+)?%s(\s+regency)?$/i',
            'kota' => '/^(kota\s+)?%s(\s+city)?$/i',
            'loose' => '/.*%s.*/i'
        ];

        foreach ($parts as $part) {
            $p = trim(preg_replace('/\s+\d+/', '', $part));
            foreach ($areas as $key => $standard) {
                $escaped = preg_quote($key, '/');
                if (preg_match(sprintf($patterns['kabupaten'], $escaped), $p)
                    || preg_match(sprintf($patterns['kota'], $escaped), $p)
                    || preg_match(sprintf($patterns['loose'], $escaped), $p)) {
                    return $standard;
                }
            }
        }

        if (stripos($address, 'bali') !== false) {
            return 'Bali';
        }

        return null;
    }
}


