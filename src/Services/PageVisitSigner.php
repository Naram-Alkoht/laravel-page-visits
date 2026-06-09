<?php

declare(strict_types=1);

namespace Naram\PageVisits\Services;

use Illuminate\Support\Facades\Config;

/**
 * Signs and verifies page keys with a keyed HMAC so the tracking endpoint only
 * accepts page keys that were generated server-side. A legitimately obtained
 * (page key, signature) pair can still be replayed, but that abuse is bounded by
 * the ip+ua dedupe window in {@see PageVisitTracker}.
 */
final class PageVisitSigner
{
    public function sign(string $pageKey): string
    {
        return hash_hmac('sha256', $pageKey, $this->secret());
    }

    public function verify(string $pageKey, string $signature): bool
    {
        return hash_equals($this->sign($pageKey), $signature);
    }

    private function secret(): string
    {
        return Config::string('app.key');
    }
}
