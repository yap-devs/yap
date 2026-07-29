<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('affiliate_promoters')
            ->select(['id', 'code', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(100, function (Collection $promoters): void {
                $codes = $promoters->map(fn (object $promoter): array => [
                    'promoter_id' => $promoter->id,
                    'code' => $promoter->code,
                    'type' => 'system',
                    'status' => 'active',
                    'disabled_at' => null,
                    'created_at' => $promoter->created_at,
                    'updated_at' => $promoter->updated_at,
                    'deleted_at' => null,
                ])->all();

                DB::table('affiliate_referral_codes')->insertOrIgnore($codes);
            });

        $has_unregistered_promoter_code = DB::table('affiliate_promoters as promoters')
            ->leftJoin('affiliate_referral_codes as codes', function (JoinClause $join): void {
                $join->on('codes.promoter_id', '=', 'promoters.id')
                    ->on('codes.code', '=', 'promoters.code')
                    ->where('codes.type', 'system');
            })
            ->whereNull('codes.id')
            ->exists();

        throw_if($has_unregistered_promoter_code, RuntimeException::class, 'Not every promoter code was registered.');
    }

    public function down(): void
    {
        // Use a forward migration; deleting system codes would invalidate referral links created later.
    }
};
