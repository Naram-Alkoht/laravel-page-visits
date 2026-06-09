<?php

declare(strict_types=1);

namespace Naram\PageVisits\Data;

final readonly class PendingPageVisit
{
    public function __construct(
        public string $member,
        public string $date,
        public string $pageKey,
        public ?string $routeName,
        public ?string $visitableType,
        public ?string $visitableId,
        public int $views,
    ) {}
}
