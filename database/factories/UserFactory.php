<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $now = now();

        return ['public_id' => (string) Str::uuid(), 'name' => fake()->name(), 'email' => strtolower(fake()->unique()->safeEmail()),
            'email_verified_at' => $now, 'password' => static::$password ??= Hash::make('testing-only-password'),
            'profile' => 'standard', 'status' => 'active', 'version' => 1, 'remember_token' => null, 'created_at' => $now, 'updated_at' => $now];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    public function consultant(): static
    {
        return $this->state(fn (): array => ['profile' => 'consultant']);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
