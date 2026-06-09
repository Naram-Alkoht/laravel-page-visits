<?php

declare(strict_types=1);

namespace Naram\PageVisits\Services;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Config;
use Naram\PageVisits\Contracts\PageVisitStore;
use Naram\PageVisits\Data\PageVisitContext;
use Naram\PageVisits\Data\PendingPageVisit;
use Redis as PhpRedis;
use RuntimeException;

final readonly class RedisPageVisitStore implements PageVisitStore
{
    public function __construct(
        private RedisFactory $redis,
    ) {}

    public function markAsSeen(PageVisitContext $pageVisitContext, string $fingerprint, int $ttl): bool
    {
        return $this->connection()->set(
            $this->seenKey($pageVisitContext, $fingerprint),
            '1',
            'ex',
            $ttl,
            'nx',
        );
    }

    public function increment(PageVisitContext $pageVisitContext): void
    {
        $member = $pageVisitContext->aggregateMember();
        $ttl = Config::integer('page-visits.pending_ttl_seconds');

        $this->client()->eval(
            $this->incrementScript(),
            [
                $this->pendingIndexKey(),
                $this->countKey($member),
                $this->metaKey($member),
                $member,
                (string) $ttl,
                $pageVisitContext->date,
                $pageVisitContext->pageKey,
                $pageVisitContext->routeName ?? '',
                $pageVisitContext->visitableType ?? '',
                $pageVisitContext->visitableId ?? '',
            ],
            3,
        );
    }

    /**
     * @return array<int, PendingPageVisit>
     */
    public function pending(): array
    {
        /** @var array<int, string> $members */
        $members = $this->connection()->smembers($this->pendingIndexKey());

        if ($members === []) {
            return [];
        }

        $ttl = Config::integer('page-visits.pending_ttl_seconds');

        $client = $this->client();
        $client->multi(PhpRedis::PIPELINE);

        foreach ($members as $member) {
            $client->eval(
                $this->claimScript(),
                [$this->countKey($member), $this->metaKey($member), $this->flushKey($member), (string) $ttl],
                3,
            );
        }

        $responses = $client->exec();

        if (! is_array($responses)) {
            return [];
        }

        $pendingPageVisits = [];

        foreach ($members as $index => $member) {
            $pendingPageVisit = $this->toPendingPageVisit($member, $responses[$index] ?? false);

            if ($pendingPageVisit instanceof PendingPageVisit) {
                $pendingPageVisits[] = $pendingPageVisit;
            }
        }

        return $pendingPageVisits;
    }

    public function commit(PendingPageVisit $pendingPageVisit): void
    {
        $member = $pendingPageVisit->member;

        $this->client()->eval(
            $this->commitScript(),
            [
                $this->flushKey($member),
                $this->metaKey($member),
                $this->pendingIndexKey(),
                $this->countKey($member),
                $member,
            ],
            4,
        );
    }

    private function toPendingPageVisit(string $member, mixed $response): ?PendingPageVisit
    {
        if (! is_array($response)) {
            return null;
        }

        $rawViews = $response[0] ?? 0;
        $views = is_numeric($rawViews) ? (int) $rawViews : 0;
        $metadata = $this->hashToArray($response[1] ?? []);

        if ($views < 1 || $metadata === []) {
            return null;
        }

        return new PendingPageVisit(
            member: $member,
            date: $metadata['date'] ?? '',
            pageKey: $metadata['page_key'] ?? '',
            routeName: blank($metadata['route_name'] ?? '') ? null : $metadata['route_name'],
            visitableType: blank($metadata['visitable_type'] ?? '') ? null : $metadata['visitable_type'],
            visitableId: blank($metadata['visitable_id'] ?? '') ? null : $metadata['visitable_id'],
            views: $views,
        );
    }

    /**
     * @return array<string, string>
     */
    private function hashToArray(mixed $hash): array
    {
        if (! is_array($hash)) {
            return [];
        }

        $result = [];
        $count = count($hash);

        for ($index = 0; $index + 1 < $count; $index += 2) {
            $key = $hash[$index];
            $value = $hash[$index + 1];

            if (is_scalar($key) && is_scalar($value)) {
                $result[(string) $key] = (string) $value;
            }
        }

        return $result;
    }

    private function incrementScript(): string
    {
        return <<<'LUA'
        redis.call('SADD', KEYS[1], ARGV[1])
        redis.call('INCR', KEYS[2])
        redis.call('EXPIRE', KEYS[2], ARGV[2])
        redis.call('HSET', KEYS[3], 'date', ARGV[3], 'page_key', ARGV[4], 'route_name', ARGV[5], 'visitable_type', ARGV[6], 'visitable_id', ARGV[7])
        redis.call('EXPIRE', KEYS[3], ARGV[2])
        return 1
        LUA;
    }

    private function claimScript(): string
    {
        return <<<'LUA'
        local total = 0
        local staged = redis.call('GET', KEYS[3])
        if staged then
            total = total + tonumber(staged)
        end
        local current = redis.call('GET', KEYS[1])
        if current then
            total = total + tonumber(current)
            redis.call('DEL', KEYS[1])
        end
        if total == 0 then
            return false
        end
        redis.call('SET', KEYS[3], total, 'EX', tonumber(ARGV[1]))
        return { total, redis.call('HGETALL', KEYS[2]) }
        LUA;
    }

    private function commitScript(): string
    {
        return <<<'LUA'
        redis.call('DEL', KEYS[1])
        if redis.call('EXISTS', KEYS[4]) == 0 then
            redis.call('DEL', KEYS[2])
            redis.call('SREM', KEYS[3], ARGV[1])
        end
        return 1
        LUA;
    }

    private function connection(): PhpRedisConnection
    {
        $connection = $this->redis->connection(Config::string('page-visits.redis_connection'));

        if (! $connection instanceof PhpRedisConnection) {
            throw new RuntimeException('The page visits Redis connection must use phpredis.');
        }

        return $connection;
    }

    private function client(): PhpRedis
    {
        $client = $this->connection()->client();

        if (! $client instanceof PhpRedis) {
            throw new RuntimeException('The page visits Redis connection must use phpredis.');
        }

        return $client;
    }

    private function pendingIndexKey(): string
    {
        return $this->prefix().':pending';
    }

    private function seenKey(PageVisitContext $pageVisitContext, string $fingerprint): string
    {
        return sprintf(
            '%s:seen:%s:%s',
            $this->prefix(),
            $pageVisitContext->aggregateHash(),
            hash('sha1', $fingerprint),
        );
    }

    private function countKey(string $member): string
    {
        return sprintf('%s:count:%s', $this->prefix(), $member);
    }

    private function metaKey(string $member): string
    {
        return sprintf('%s:meta:%s', $this->prefix(), $member);
    }

    private function flushKey(string $member): string
    {
        return sprintf('%s:flush:%s', $this->prefix(), $member);
    }

    private function prefix(): string
    {
        return Config::string('page-visits.redis_prefix');
    }
}
