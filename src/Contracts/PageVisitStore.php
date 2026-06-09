<?php

declare(strict_types=1);

namespace Naram\PageVisits\Contracts;

use Naram\PageVisits\Data\PageVisitContext;
use Naram\PageVisits\Data\PendingPageVisit;

interface PageVisitStore
{
    /**
     * Record a fingerprint as seen for the dedupe window. Returns false when the
     * fingerprint was already seen (i.e. the visit should not be counted again).
     */
    public function markAsSeen(PageVisitContext $pageVisitContext, string $fingerprint, int $ttl): bool;

    public function increment(PageVisitContext $pageVisitContext): void;

    /**
     * Claim the buffered aggregates for flushing.
     *
     * @return array<int, PendingPageVisit>
     */
    public function pending(): array;

    public function commit(PendingPageVisit $pendingPageVisit): void;
}
