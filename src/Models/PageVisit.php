<?php

declare(strict_types=1);

namespace Naram\PageVisits\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $page_key
 * @property string|null $route_name
 * @property string|null $visitable_type
 * @property string|null $visitable_id
 * @property string $date
 * @property int $views
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
final class PageVisit extends Model
{
    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'integer',
            'page_key' => 'string',
            'route_name' => 'string',
            'visitable_type' => 'string',
            'visitable_id' => 'string',
            'date' => 'date',
            'views' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function visitable(): MorphTo
    {
        return $this->morphTo();
    }
}
