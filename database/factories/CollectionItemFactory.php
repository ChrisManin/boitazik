<?php

namespace Database\Factories;

use App\Enums\ItemCondition;
use App\Models\Album;
use App\Models\Release;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CollectionItem>
 */
class CollectionItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'release_id' => Release::factory(),
            // Keep the item consistent with the album of its release
            'album_id' => fn (array $attributes) => Release::find($attributes['release_id'])->album_id,
            'condition' => fake()->randomElement(ItemCondition::cases()),
            'acquired_at' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }

    /**
     * An item whose exact edition is unknown: only the album is known.
     */
    public function withoutRelease(): static
    {
        return $this->state([
            'release_id' => null,
            'album_id' => Album::factory(),
        ]);
    }
}
