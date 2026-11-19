<?php

namespace App\Modules\Entitlements;

/**
 * Feature codes in the `features` table. Business logic refers to these
 * codes, never to plan names or hard-coded numbers.
 */
final class FeatureCode
{
    public const STORAGE_MAX_BYTES = 'storage.max_bytes';

    public const FILES_MAX_UPLOAD_BYTES = 'files.max_upload_bytes';

    public const SPACE_MAX_MEMBERS = 'space.max_members';

    public const SPACE_MAX_FAMILY_SPACES = 'space.max_family_spaces';

    public const RECORDS_MAX_COUNT = 'records.max_count';

    public const REMINDERS_MAX_ACTIVE = 'reminders.max_active';

    public const REMINDERS_SMS_PER_MONTH = 'reminders.sms_per_month';

    /** Always true, whatever the plan says: data is never held hostage. */
    public const EXPORT_ENABLED = 'export.enabled';
}
