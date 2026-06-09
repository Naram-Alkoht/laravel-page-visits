<?php

declare(strict_types=1);

namespace Naram\PageVisits\Data;

final readonly class PageVisitViewData
{
    public function __construct(
        public string $pageKey,
        public string $signature,
    ) {}
}
