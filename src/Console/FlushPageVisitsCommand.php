<?php

declare(strict_types=1);

namespace Naram\PageVisits\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Naram\PageVisits\Actions\PageVisitFlusher;

#[Signature('page-visits:flush')]
#[Description('Flush pending page visit counters from Redis into the database')]
final class FlushPageVisitsCommand extends Command
{
    public function handle(PageVisitFlusher $flusher): int
    {
        $flushedVisits = $flusher->flush();

        $this->info(sprintf('Flushed %d pending page visit aggregate(s).', $flushedVisits));

        return self::SUCCESS;
    }
}
