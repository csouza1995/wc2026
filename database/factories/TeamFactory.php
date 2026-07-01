<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->country(),
            'fifa_code' => fake()->unique()->lexify('???'),
            'flag_url' => null,
            'confederation' => fake()->randomElement(['UEFA', 'CONMEBOL', 'CONCACAF', 'CAF', 'AFC', 'OFC']),
        ];
    }
}
