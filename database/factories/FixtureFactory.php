<?php

namespace Database\Factories;

use App\Enums\FixtureRound;
use App\Enums\FixtureStatus;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fixture>
 */
class FixtureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round' => FixtureRound::Group,
            'home_team_id' => Team::factory(),
            'away_team_id' => Team::factory(),
            'kickoff_at' => fake()->dateTimeBetween('now', '+1 month'),
            'status' => FixtureStatus::Scheduled,
        ];
    }

    public function finished(int $homeScore, int $awayScore): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FixtureStatus::Finished,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'kickoff_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }
}
