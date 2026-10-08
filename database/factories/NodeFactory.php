<?php

namespace Database\Factories;

use App\Models\Node;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Node> */
class NodeFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->word(), 'agent_token_hash' => hash('sha256', fake()->uuid()), 'enabled' => true];
    }
}
