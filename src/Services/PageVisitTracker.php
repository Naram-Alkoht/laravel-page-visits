<?php

declare(strict_types=1);

namespace Naram\PageVisits\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Naram\PageVisits\Contracts\PageVisitStore;
use Naram\PageVisits\Data\PageVisitContext;

final readonly class PageVisitTracker
{
    public function __construct(
        private PageVisitStore $store,
    ) {}

    public function track(PageVisitContext $pageVisitContext, ?string $ipAddress, ?string $userAgent): void
    {
        if (! Config::boolean('page-visits.enabled')) {
            return;
        }

        if (Config::boolean('page-visits.ignore_bots') && $this->isBot($userAgent)) {
            return;
        }

        $ttl = $this->resolveTtl($pageVisitContext);
        $fingerprint = $this->resolveFingerprint($pageVisitContext, $ipAddress, $userAgent);

        if (! $this->store->markAsSeen($pageVisitContext, $fingerprint, $ttl)) {
            return;
        }

        $this->store->increment($pageVisitContext);
    }

    public function shouldTrackRequest(bool $isAuthenticated, ?string $userAgent): bool
    {
        if (! Config::boolean('page-visits.enabled')) {
            return false;
        }

        if (Config::boolean('page-visits.exclude_authenticated_users') && $isAuthenticated) {
            return false;
        }

        return ! (Config::boolean('page-visits.ignore_bots') && $this->isBot($userAgent));
    }

    private function resolveTtl(PageVisitContext $pageVisitContext): int
    {
        /** @var array<string, int> $routeTtls */
        $routeTtls = Config::array('page-visits.route_ttls');

        foreach ($routeTtls as $pattern => $ttl) {
            if (Str::is($pattern, $pageVisitContext->routeName ?? '')) {
                return $ttl;
            }
        }

        return Config::integer('page-visits.default_ttl_seconds');
    }

    private function resolveFingerprint(PageVisitContext $pageVisitContext, ?string $ipAddress, ?string $userAgent): string
    {
        $normalizedIpAddress = $ipAddress ?? 'unknown-ip';
        $normalizedUserAgent = Str::lower($userAgent ?? 'unknown-agent');

        return hash('sha256', implode('|', [
            $pageVisitContext->pageKey,
            $normalizedIpAddress,
            $normalizedUserAgent,
        ]));
    }

    private function isBot(?string $userAgent): bool
    {
        $userAgent = Str::lower($userAgent ?? '');

        if ($userAgent === '') {
            return true;
        }

        /** @var array<int, string> $fragments */
        $fragments = Config::array('page-visits.bot_user_agent_fragments');

        return array_any(
            $fragments,
            fn (string $fragment): bool => Str::contains($userAgent, Str::lower($fragment))
        );
    }
}
