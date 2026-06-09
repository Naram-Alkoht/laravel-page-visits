# Laravel Page Visits

Redis-buffered, signature-verified page-visit tracking for Laravel.

Page views are counted in Redis on each request and folded into the database on a
schedule, so high-traffic pages never write a row per hit. Each tracked page is
signed with an HMAC so the front-end tracker can't be used to inflate arbitrary
counters.

## Requirements

- PHP `^8.4`
- Laravel `^13.0`
- Redis (`ext-redis` / phpredis)

## Installation

```bash
composer require naram/laravel-page-visits
```

The service provider is auto-discovered. Publish and run the migration:

```bash
php artisan vendor:publish --tag=page-visits-migrations
php artisan migrate
```

Publish the config when you need to change defaults:

```bash
php artisan vendor:publish --tag=page-visits-config
```

Available publish tags: `page-visits-config`, `page-visits-migrations`,
`page-visits-views`, `page-visits-assets`.

## Configuration

`config/page-visits.php` (env keys in parentheses):

| Key | Default | Purpose |
| --- | --- | --- |
| `enabled` (`PAGE_VISITS_ENABLED`) | `true` | Master switch. |
| `redis_connection` (`PAGE_VISITS_REDIS_CONNECTION`) | `cache` | Redis connection used for buffering. |
| `redis_prefix` (`PAGE_VISITS_REDIS_PREFIX`) | `page-visits` | Key namespace. |
| `default_ttl_seconds` (`PAGE_VISITS_DEFAULT_TTL_SECONDS`) | `21600` | De-dupe window per visitor fingerprint. |
| `route_ttls` | `[]` | Per-route TTL overrides keyed by route. |
| `pending_ttl_seconds` (`PAGE_VISITS_PENDING_TTL_SECONDS`) | `259200` | Safety TTL on staged counts awaiting commit. |
| `exclude_authenticated_users` | `false` | Skip logged-in users. |
| `ignore_bots` | `true` | Skip requests matching `bot_user_agent_fragments`. |
| `bot_user_agent_fragments` | see config | Substrings that mark a user agent as a bot. |
| `visitable_types` | `[]` | FQCNs of models that may be tracked as morphable visitables. |
| `route` | see below | Endpoint the front-end tracker posts to. |

The tracking endpoint is registered by the package from the `route` config:

```php
'route' => [
    'uri' => env('PAGE_VISITS_ROUTE_URI', '/page-visits'),
    'name' => 'page-visits.store',
    'middleware' => ['web', 'throttle:60,1'],
],
```

## Usage

### Track a page

Drop the Blade component into any page you want to count. It renders a hidden,
signed element and emits nothing when the request shouldn't be tracked (bot,
excluded user, disabled, etc.):

```blade
<x-visit-tracker />
```

Wire the front-end tracker so the element triggers a POST to the endpoint. Publish
the JS with `--tag=page-visits-assets` (lands in `resources/js/vendor/page-visits`)
and import it in your bundle:

```js
import { trackCurrentPageVisit } from "./vendor/page-visits/page-visit-tracker";

trackCurrentPageVisit();
```

### Track a model's detail pages

Add the model's class to `visitable_types`, then use the trait:

```php
use Naram\PageVisits\Concerns\HasPageVisits;

final class Post extends Model
{
    use HasPageVisits;
}
```

```php
$post->pageVisits; // MorphMany<PageVisit>
$post->pageVisits()->sum('visits');
```

### Flushing buffered counts

Counts live in Redis until flushed to the database. Run the command on a schedule
in your app's `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
use Naram\PageVisits\Console\FlushPageVisitsCommand;

Schedule::command(FlushPageVisitsCommand::class)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
```

> **Note:** the flush is intentionally *at-least-once*, not exactly-once. A crash
> in the narrow window after the database upsert but before the Redis commit can
> re-apply a staged total, double-counting those views. This is an accepted
> trade-off for page-view analytics. If you ever need exact counts, swap the store
> for an idempotent flush backed by a dedup ledger.

## Extending

`Naram\PageVisits\Contracts\PageVisitStore` is the seam for the buffering backend.
The package binds it to `RedisPageVisitStore`; rebind it in a service provider to
provide your own implementation.

## License

MIT. See [LICENSE](LICENSE).
