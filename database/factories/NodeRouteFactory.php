<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\NodeRoute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NodeRoute> */
class NodeRouteFactory extends Factory
{
    public function definition(): array
    {
        return ['node_id' => Node::factory(), 'name' => fake()->word(), 'server' => 'node.example.invalid', 'port' => 443, 'inbound_tag' => 'yap-vmess', 'listen_port' => fake()->unique()->numberBetween(10000, 60000), 'rate' => '1.00', 'enabled' => true];
    }
}
