<?php

declare(strict_types=1);

namespace Naram\PageVisits\Actions;

use Naram\PageVisits\Contracts\PageVisitStore;
use Naram\PageVisits\Models\PageVisit;

final readonly class PageVisitFlusher
{
    public function __construct(
        private PageVisitStore $store,
    ) {}

    /**
     * Drain the buffered counters into the database.
     *
     * This is at-least-once, not exactly-once. The store's claim() stages the
     * count and commit() clears it only after the upsert succeeds, so a crash
     * *before* the upsert rolls the count forward (no loss). The inverse window
     * — a crash *after* the upsert but before commit() — re-applies the staged
     * total on the next cycle, double-counting those views. Accepted for
     * page-view analytics; switch to an idempotent flush (dedup ledger) if exact
     * counts are ever required.
     */
    public function flush(): int
    {
        $flushedVisits = 0;

        foreach ($this->store->pending() as $pendingPageVisit) {
            PageVisit::applyPending($pendingPageVisit);

            $this->store->commit($pendingPageVisit);
            $flushedVisits++;
        }

        return $flushedVisits;
    }
}
