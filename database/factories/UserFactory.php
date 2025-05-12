<?php

namespace Database\Factories;

use Domains\Users\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'id' => str()->ulid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => self::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => UserStatus::ACTIVE,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => UserStatus::ACTIVE]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => UserStatus::INACTIVE]);
    }
}
