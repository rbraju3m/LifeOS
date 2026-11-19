<?php

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Shared\Http\Requests\StrictFormRequest;

class UpdatePreferencesRequest extends StrictFormRequest
{
    public function rules(): array
    {
        return [
            'date_format' => ['sometimes', 'in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD'],
            'time_format' => ['sometimes', 'in:12h,24h'],
            'week_start' => ['sometimes', 'integer', 'between:0,6'],
            'use_bengali_digits' => ['sometimes', 'boolean'],
            'default_reminder_time' => ['sometimes', 'date_format:H:i'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_end'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:quiet_hours_start'],
            'theme' => ['sometimes', 'in:system,light,dark'],
        ];
    }
}
