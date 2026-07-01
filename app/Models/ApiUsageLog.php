<?php

namespace App\Models;

use App\Enums\DataSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property DataSource $source
 * @property int $requests_count
 */
#[Fillable(['date', 'source', 'requests_count'])]
class ApiUsageLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'source' => DataSource::class,
        ];
    }
}
