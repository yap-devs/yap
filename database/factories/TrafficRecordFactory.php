<?php

namespace Database\Factories;

use App\Models\NodeRoute;
use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TrafficRecord> */
class TrafficRecordFactory extends Factory
{
    public function definition(): array
    {
        return ['traffic_batch_id' => TrafficBatch::factory(), 'node_route_id' => fn (array $attributes) => NodeRoute::factory()->create(['node_id' => TrafficBatch::findOrFail($attributes['traffic_batch_id'])->node_id])->id, 'user_id' => User::factory(), 'raw_uplink' => 100, 'raw_downlink' => 200, 'applied_rate' => '1.00', 'billed_uplink' => 100, 'billed_downlink' => 200];
    }
}
