<?php

namespace App\Console\Commands;

use App\Models\StockLevel;
use App\Services\Inventory\StockLedger;
use Illuminate\Console\Command;

/**
 * Replays the movement ledger to regenerate the cached stock_levels table.
 *
 * The ledger is the source of truth; this table is only a cache. If the two ever
 * disagree, the ledger wins and this command is how you say so.
 */
class RebuildStockLevels extends Command
{
    protected $signature = 'stock:rebuild {--check : Report drift without writing anything}';

    protected $description = 'Rebuild cached stock levels by replaying the movement ledger';

    public function handle(StockLedger $ledger): int
    {
        if ($this->option('check')) {
            return $this->check();
        }

        $rows = $ledger->rebuildCache();

        $this->info("Rebuilt {$rows} stock level rows from the movement ledger.");

        return self::SUCCESS;
    }

    /**
     * Compares cache against ledger without touching either. Safe to run on a
     * schedule as a canary.
     */
    private function check(): int
    {
        $cached = StockLevel::query()
            ->get()
            ->mapWithKeys(fn (StockLevel $l) => [$l->product_id.':'.$l->warehouse_id => round((float) $l->quantity, 3)]);

        $actual = \App\Models\StockMovement::query()
            ->selectRaw('product_id, warehouse_id, SUM(quantity) as total')
            ->groupBy('product_id', 'warehouse_id')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->product_id.':'.$r->warehouse_id => round((float) $r->total, 3)]);

        $drift = [];

        foreach ($actual as $key => $value) {
            if (($cached[$key] ?? 0.0) !== $value) {
                $drift[$key] = ['cached' => $cached[$key] ?? 0.0, 'ledger' => $value];
            }
        }

        foreach ($cached as $key => $value) {
            if (! $actual->has($key) && $value !== 0.0) {
                $drift[$key] = ['cached' => $value, 'ledger' => 0.0];
            }
        }

        if ($drift === []) {
            $this->info('Stock levels agree with the ledger.');

            return self::SUCCESS;
        }

        $this->error(count($drift).' stock level rows have drifted:');
        $this->table(
            ['product:warehouse', 'cached', 'ledger'],
            collect($drift)->map(fn ($d, $k) => [$k, $d['cached'], $d['ledger']])->values()->all(),
        );

        return self::FAILURE;
    }
}
