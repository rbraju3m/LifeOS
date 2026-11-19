<?php

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string|null $phone_e164
 * @property string $password
 * @property Carbon|null $two_factor_confirmed_at
 * @property string $locale
 * @property string $timezone
 * @property string $country_code
 * @property string $currency
 * @property UserStatus $status
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read UserPreference|null $preferences
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, TwoFactorAuthenticatable;

    protected $dateFormat = 'Y-m-d H:i:s.v';

    /**
     * Never mass-assignable: status, verification timestamps, 2FA columns,
     * last_login_at. Those are set by actions with forceFill().
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'phone_e164', 'locale', 'timezone', 'country_code', 'currency',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    /**
     * Defaults for columns that code reads on a freshly created (not yet
     * reloaded) model; strict mode would otherwise reject them as missing.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'locale' => 'bn',
        'timezone' => 'Asia/Dhaka',
        'country_code' => 'BD',
        'currency' => 'BDT',
        'email_verified_at' => null,
        'phone_e164' => null,
        'phone_verified_at' => null,
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
        'deletion_requested_at' => null,
        'last_login_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Emails are stored trimmed and lower-cased.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => mb_strtolower(trim($value)));
    }

    /**
     * @return HasOne<UserPreference, $this>
     */
    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
