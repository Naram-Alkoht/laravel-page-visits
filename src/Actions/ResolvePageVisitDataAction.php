<?php

declare(strict_types=1);

namespace Naram\PageVisits\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Naram\PageVisits\Data\PageVisitViewData;
use Naram\PageVisits\Services\PageVisitSigner;

final readonly class ResolvePageVisitDataAction
{
    public function __construct(
        private PageVisitSigner $signer,
    ) {}

    public function execute(Request $request): ?PageVisitViewData
    {
        $routeName = $request->route()?->getName();

        if (! is_string($routeName) || $routeName === '') {
            return null;
        }

        $pageKey = $this->resolvePageKey($routeName, $this->resolveVisitable($request));

        return new PageVisitViewData(
            pageKey: $pageKey,
            signature: $this->signer->sign($pageKey),
        );
    }

    private function resolveVisitable(Request $request): ?Model
    {
        /** @var Collection<int|string, mixed> $parameters */
        $parameters = collect($request->route()?->parameters() ?? []);

        $visitable = $parameters->first(fn (mixed $parameter): bool => $parameter instanceof Model);

        return $visitable instanceof Model ? $visitable : null;
    }

    private function resolvePageKey(string $routeName, ?Model $visitable): string
    {
        if ($visitable instanceof Model && $this->isAllowedVisitableType($visitable->getMorphClass())) {
            $visitableId = $visitable->getKey();

            return sprintf('%s:%s:%s', $routeName, $visitable->getMorphClass(), is_scalar($visitableId) ? $visitableId : '');
        }

        return $routeName;
    }

    private function isAllowedVisitableType(string $visitableType): bool
    {
        return in_array($visitableType, Config::array('page-visits.visitable_types'), strict: true);
    }
}
