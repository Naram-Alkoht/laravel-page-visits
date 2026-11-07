<?php

declare(strict_types=1);

namespace Naram\PageVisits\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;
use Naram\PageVisits\Actions\ResolvePageVisitDataAction;

final class VisitTracker extends Component
{
    public function __construct(
        public ?Model $visitable = null,
    ) {}

    public function render(): View|string
    {
        $pageVisitData = resolve(ResolvePageVisitDataAction::class)->execute(request(), $this->visitable);

        if ($pageVisitData === null) {
            return '';
        }

        return view('page-visits::visit-tracker', [
            'pageVisitData' => $pageVisitData,
        ]);
    }
}
