<?php

declare(strict_types=1);

namespace Naram\PageVisits\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Naram\PageVisits\Data\PageVisitContext;

final class ResolvePageVisitContextAction
{
    public function execute(string $pageKey): ?PageVisitContext
    {
        $segments = explode(':', $pageKey, 3);

        if (count($segments) === 3) {
            [$routeName, $visitableType, $visitableId] = $segments;

            if (! $this->isAllowedRoute($routeName) || ! $this->isAllowedVisitableType($visitableType) || ! $this->visitableExists($visitableType, $visitableId)) {
                return null;
            }

            return new PageVisitContext(
                pageKey: $pageKey,
                routeName: $routeName,
                date: now()->toDateString(),
                visitableType: $visitableType,
                visitableId: $visitableId,
            );
        }

        if (! $this->isAllowedRoute($pageKey)) {
            return null;
        }

        return new PageVisitContext(
            pageKey: $pageKey,
            routeName: $pageKey,
            date: now()->toDateString(),
            visitableType: null,
            visitableId: null,
        );
    }

    private function isAllowedRoute(string $routeName): bool
    {
        return $routeName !== '' && Route::has($routeName);
    }

    private function isAllowedVisitableType(string $visitableType): bool
    {
        return in_array($visitableType, Config::array('page-visits.visitable_types'), strict: true);
    }

    private function visitableExists(string $visitableType, string $visitableId): bool
    {
        if ($visitableId === '') {
            return false;
        }

        /** @var class-string<Model> $visitableType */
        return $visitableType::query()->whereKey($visitableId)->exists();
    }
}
