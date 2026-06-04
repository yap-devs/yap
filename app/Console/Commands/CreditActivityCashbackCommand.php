<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use App\Models\BalanceDetail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditActivityCashbackCommand extends Command
{
    protected $signature = 'app:credit-activity-cashback-command {date : Consumption date to reward, formatted as YYYY-MM-DD} {--ratio=1 : Cashback ratio, use 1 for 100% cashback} {--description= : Balance detail description, supports {date}} {--execute : Actually credit balances instead of previewing}';

    protected $description = 'Credit promotional cashback for user balance consumed on a day';

    public function handle(): int
    {
        $target_date = $this->targetDate();
        $description = $this->descriptionFor($target_date);
        $execute = (bool) $this->option('execute');
        $ratio = (float) $this->option('ratio');
        if ($ratio <= 0) {
            $this->info('Activity cashback ratio is not positive.');

            return self::SUCCESS;
        }

        $credited_count = 0;
        $credited_amount = '0.00';

        BalanceDetail::query()
            ->select('user_id')
            ->selectRaw('SUM(ABS(amount)) as consumed_amount')
            ->where('amount', '<', 0)
            ->whereBetween('created_at', [$target_date->startOfDay(), $target_date->endOfDay()])
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->chunk(100, function ($rows) use ($ratio, $description, $execute, &$credited_count, &$credited_amount): void {
                foreach ($rows as $row) {
                    $cashback_amount = number_format(round((float) $row->consumed_amount * $ratio, 2), 2, '.', '');
                    if ((float) $cashback_amount <= 0) {
                        continue;
                    }

                    if ($this->handleUser((int) $row->user_id, $cashback_amount, $description, $execute)) {
                        $credited_count++;
                        $credited_amount = bcadd($credited_amount, $cashback_amount, 2);
                    }
                }
            });

        if ($execute && $credited_count > 0) {
            GenerateClashProfileLink::dispatch();
        }

        $action = $execute ? 'Credited' : 'Would credit';
        $this->info("{$action} {$credited_count} users with {$credited_amount} activity cashback for {$target_date->toDateString()}.");

        return self::SUCCESS;
    }

    private function handleUser(int $user_id, string $cashback_amount, string $description, bool $execute): bool
    {
        if (! $execute) {
            return User::query()
                ->whereKey($user_id)
                ->whereDoesntHave('balanceDetails', fn ($query) => $query->where('description', $description))
                ->exists();
        }

        $credited = false;

        DB::transaction(function () use ($user_id, $cashback_amount, $description, &$credited): void {
            /** @var User|null $user */
            $user = User::query()->lockForUpdate()->find($user_id);
            if (! $user) {
                return;
            }

            if ($user->balanceDetails()->where('description', $description)->exists()) {
                return;
            }

            $user->balance = bcadd((string) $user->balance, $cashback_amount, 2);
            $user->save();

            $user->balanceDetails()->create([
                'amount' => $cashback_amount,
                'description' => $description,
            ]);

            $credited = true;
        });

        return $credited;
    }

    private function targetDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->argument('date'))->startOfDay();
    }

    private function descriptionFor(CarbonImmutable $target_date): string
    {
        $description = $this->option('description') ?: 'Activity cashback for {date}';

        return Str::replace('{date}', $target_date->toDateString(), $description);
    }
}
