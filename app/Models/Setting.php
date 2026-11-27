<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One site-wide setting. Read and write settings through App\Services\SiteSettings,
 * which caches them, rather than through this model.
 *
 * @property int $id
 * @property string $name
 * @property string|null $value
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'value'])]
class Setting extends Model
{
    //
}
