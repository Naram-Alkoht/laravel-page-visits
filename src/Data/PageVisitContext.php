<?php

declare(strict_types=1);

namespace Naram\PageVisits\Data;

final readonly class PageVisitContext
{
    public function __construct(
        public string $pageKey,
        public ?string $routeName,
        public string $date,
        public ?string $visitableType,
        public ?string $visitableId,
    ) {}

    public function aggregateHash(): string
    {
        return hash('sha1', implode('|', [
            $this->pageKey,
            $this->routeName ?? 'null',
            $this->visitableType ?? 'null',
            $this->visitableId ?? 'null',
        ]));
    }

    public function aggregateMember(): string
    {
        return sprintf('%s|%s', $this->date, $this->aggregateHash());
    }
}
