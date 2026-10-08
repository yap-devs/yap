<?php

namespace Database\Factories;

use App\Models\Node;
use App\Models\TrafficBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrafficBatch> */
class TrafficBatchFactory extends Factory
{
    public function definition(): array
    {
        return ['node_id' => Node::factory(), 'batch_uuid' => fake()->uuid(), 'payload_hash' => hash('sha256', fake()->uuid()), 'received_at' => now()];
    }
}
