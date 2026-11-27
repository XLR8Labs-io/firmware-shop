<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One site-wide setting. Read and write settings through App\Services\SiteSettings,
 * which caches them, rather than through this model.
 *
 * @property int $id
 * @property string $name
 * @property string|null $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'value'])]
class Setting extends Model
{
    //
}
