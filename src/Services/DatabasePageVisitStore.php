<?php

declare(strict_types=1);

namespace Naram\PageVisits\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Lottery;
use Naram\PageVisits\Contracts\PageVisitStore;
use Naram\PageVisits\Data\PageVisitContext;
use Naram\PageVisits\Data\PendingPageVisit;
use Naram\PageVisits\Models\PageVisit;

/**
 * Adds each counted visit straight to the daily aggregate row, so nothing waits
 * for a flush. One page keeps one row per day, whatever its number of visits.
 */
final readonly class DatabasePageVisitStore implements PageVisitStore
{
    public function markAsSeen(PageVisitContext $pageVisitContext, string $fingerprint, int $ttl): bool
    {
        $now = now();

        Lottery::odds(1, 100)
            ->winner(fn (): int => $this->fingerprints()->where('expires_at', '<=', $now)->delete())
            ->choose();

        $this->fingerprints()
            ->where('fingerprint', $fingerprint)
            ->where('expires_at', '<=', $now)
            ->delete();

        return $this->fingerprints()->insertOrIgnore([
            'fingerprint' => $fingerprint,
            'expires_at' => $now->addSeconds($ttl),
        ]) === 1;
    }

    public function increment(PageVisitContext $pageVisitContext): void
    {
        PageVisit::applyPending(new PendingPageVisit(
            member: $pageVisitContext->aggregateMember(),
            date: $pageVisitContext->date,
            pageKey: $pageVisitContext->pageKey,
            routeName: $pageVisitContext->routeName,
            visitableType: $pageVisitContext->visitableType,
            visitableId: $pageVisitContext->visitableId,
            views: 1,
        ));
    }

    /**
     * @return array<int, PendingPageVisit>
     */
    public function pending(): array
    {
        return [];
    }

    public function commit(PendingPageVisit $pendingPageVisit): void {}

    private function fingerprints(): Builder
    {
        return DB::table('page_visit_fingerprints');
    }
}
