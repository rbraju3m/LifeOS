<?php

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserPreference
 */
class PreferencesResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'date_format' => $this->date_format,
            'time_format' => $this->time_format,
            'week_start' => $this->week_start,
            'use_bengali_digits' => $this->use_bengali_digits,
            'default_reminder_time' => self::hm($this->default_reminder_time),
            'quiet_hours_start' => self::hm($this->quiet_hours_start),
            'quiet_hours_end' => self::hm($this->quiet_hours_end),
            'theme' => $this->theme,
        ];
    }

    private static function hm(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }
}
