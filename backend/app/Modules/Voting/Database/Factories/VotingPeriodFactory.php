<?php

declare(strict_types=1);

namespace App\Modules\Voting\Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\Voting\Models\VotingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VotingPeriod>
 */
final class VotingPeriodFactory extends Factory
{
    protected $model = VotingPeriod::class;

    public function definition(): array
    {
        return [
            'name' => 'Minerva Competition Voting',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDays(7),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(8),
            'is_active' => true,
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->subHour(),
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'is_active' => false,
        ]);
    }
}
