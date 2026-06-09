<?php

declare(strict_types=1);

namespace Naram\PageVisits\Http\Controllers;

use Illuminate\Http\Response;
use Naram\PageVisits\Actions\ResolvePageVisitContextAction;
use Naram\PageVisits\Http\Requests\StorePageVisitRequest;
use Naram\PageVisits\Services\PageVisitTracker;

final class PageVisitController
{
    public function __invoke(
        StorePageVisitRequest $request,
        PageVisitTracker $tracker,
        ResolvePageVisitContextAction $resolvePageVisitContext,
    ): Response {
        if (! $tracker->shouldTrackRequest($request->user() !== null, $request->userAgent())) {
            return response()->noContent();
        }

        $pageVisitContext = $resolvePageVisitContext->execute(
            $request->string('page_key')->toString(),
        );

        if ($pageVisitContext === null) {
            return response()->noContent();
        }

        $tracker->track(
            $pageVisitContext,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->noContent();
    }
}
