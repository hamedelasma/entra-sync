<?php

declare(strict_types=1);

namespace O3\EntraSync\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),
            'microsoft_id' => null,
            'department' => null,
            'job_title' => null,
            'phone' => null,
            'is_active' => true,
        ];
    }

    public function entra(?string $microsoftId = null): static
    {
        return $this->state(fn () => [
            'microsoft_id' => $microsoftId ?? fake()->uuid(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
