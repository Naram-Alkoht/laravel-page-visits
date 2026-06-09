<?php

declare(strict_types=1);

namespace Naram\PageVisits\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Naram\PageVisits\Models\PageVisit;

trait HasPageVisits
{
    /** @return MorphMany<PageVisit, $this> */
    public function pageVisits(): MorphMany
    {
        return $this->morphMany(PageVisit::class, 'visitable');
    }
}
