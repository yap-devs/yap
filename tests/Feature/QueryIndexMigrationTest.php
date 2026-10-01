<?php

use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('query indexes can be rolled back without removing records', function () {
    $user = User::factory()->create();
    $stat = $user->stats()->create(['traffic_uplink' => 10, 'traffic_downlink' => 20]);
    $traffic = require database_path('migrations/2026_10_01_114801_add_user_time_index_to_user_stats_table.php');
    $histories = require database_path('migrations/2026_10_01_114802_add_history_query_indexes.php');

    expect(Schema::hasIndex('user_stats', ['user_id', 'deleted_at', 'created_at']))->toBeTrue()
        ->and(Schema::hasIndex('user_packages', ['user_id', 'deleted_at', 'created_at', 'id']))->toBeTrue()
        ->and(Schema::hasIndex('affiliate_referrals', ['promoter_id', 'deleted_at', 'created_at', 'id']))->toBeTrue()
        ->and(Schema::hasIndex('affiliate_commissions', ['promoter_id', 'deleted_at', 'created_at', 'id']))->toBeTrue();

    $histories->down();
    $traffic->down();
    expect(Schema::hasIndex('user_stats', ['user_id', 'deleted_at', 'created_at']))->toBeFalse()
        ->and($stat->fresh()->traffic_uplink)->toBe(10);

    $traffic->up();
    $traffic->up();
    $histories->up();
    expect(Schema::hasIndex('user_stats', ['user_id', 'deleted_at', 'created_at']))->toBeTrue()
        ->and($stat->fresh()->traffic_downlink)->toBe(20);
});
