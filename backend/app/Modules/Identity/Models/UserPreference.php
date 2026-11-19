<?php

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $user_id
 * @property string $date_format
 * @property string $time_format
 * @property int $week_start
 * @property bool $use_bengali_digits
 * @property string $default_reminder_time
 * @property string|null $quiet_hours_start
 * @property string|null $quiet_hours_end
 * @property string $theme
 */
class UserPreference extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * Defaults mirror the column defaults so a freshly created row is complete
     * without a reload.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'date_format' => 'DD/MM/YYYY',
        'time_format' => '12h',
        'week_start' => 6,
        'use_bengali_digits' => false,
        'default_reminder_time' => '09:00:00',
        'theme' => 'system',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'date_format', 'time_format', 'week_start', 'use_bengali_digits',
        'default_reminder_time', 'quiet_hours_start', 'quiet_hours_end', 'theme',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'integer',
            'use_bengali_digits' => 'boolean',
            'dashboard_config' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
