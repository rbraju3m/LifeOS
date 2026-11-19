<?php

namespace Database\Factories;

use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\UserRegistered;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** The plain-text password every factory user has. */
    public const PASSWORD = 'correct-horse-battery';

    protected static ?string $passwordHash = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$passwordHash ??= Hash::make(self::PASSWORD),
            'remember_token' => Str::random(10),
            'locale' => 'en',
            'timezone' => 'Asia/Dhaka',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    public function configure(): static
    {
        // Same set-up as a real registration (preferences, personal space, …).
        return $this->afterCreating(function (User $user): void {
            $user->preferences()->firstOrCreate();
            event(new UserRegistered($user));
        });
    }
}
