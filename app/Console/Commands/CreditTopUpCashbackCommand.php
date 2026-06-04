<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreditTopUpCashbackCommand extends Command
{
    protected $signature = 'app:credit-top-up-cashback-command {date : Top-up date to reward, formatted as YYYY-MM-DD} {--ratio=1 : Cashback ratio, use 1 for 100% cashback} {--description= : Balance detail description, supports {date}} {--execute : Actually credit balances instead of previewing}';

    protected $description = 'Credit promotional cashback for user top-ups on a day';

    public function handle(): int
    {
        $target_date = $this->targetDate();
        $description = $this->descriptionFor($target_date);
        $execute = (bool) $this->option('execute');
        $ratio = (float) $this->option('ratio');
        if ($ratio <= 0) {
            $this->info('Top-up cashback ratio is not positive.');

            return self::SUCCESS;
        }

        $credited_count = 0;
        $credited_amount = '0.00';

        $rows = [];

        $user_totals = Payment::query()
            ->select('user_id')
            ->selectRaw('SUM(amount) as source_amount')
            ->where('status', Payment::STATUS_PAID)
            ->whereBetween('created_at', [$target_date->startOfDay(), $target_date->endOfDay()])
            ->groupBy('user_id')
            ->orderBy('user_id')
            ->get();

        foreach ($user_totals as $user_total) {
            $cashback_amount = number_format(round((float) $user_total->source_amount * $ratio, 2), 2, '.', '');
            if ((float) $cashback_amount <= 0) {
                continue;
            }

            if ($this->handleUser((int) $user_total->user_id, $cashback_amount, $description, $execute)) {
                $credited_count++;
                $credited_amount = bcadd($credited_amount, $cashback_amount, 2);
            }
        }

        Payment::query()
            ->with('user:id,email')
            ->where('status', Payment::STATUS_PAID)
            ->whereBetween('created_at', [$target_date->startOfDay(), $target_date->endOfDay()])
            ->orderBy('user_id')
            ->orderBy('created_at')
            ->get()
            ->each(function (Payment $payment) use (&$rows): void {
                $rows[] = [
                    $payment->user_id,
                    $payment->user?->email ?? '',
                    $payment->id,
                    $payment->gateway,
                    $payment->remote_id,
                    number_format((float) $payment->amount, 2, '.', ''),
                    $payment->created_at->toDateTimeString(),
                ];
            });

        $this->table(['User ID', 'Email', 'Payment ID', 'Gateway', 'Remote ID', 'Top Up', 'Created At'], $rows);

        if ($execute && $credited_count > 0) {
            GenerateClashProfileLink::dispatch();
        }

        $action = $execute ? 'Credited' : 'Would credit';
        $this->info("{$action} {$credited_count} users with {$credited_amount} top-up cashback for {$target_date->toDateString()}.");

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
        $description = $this->option('description') ?: 'Top-up cashback for {date}';

        return Str::replace('{date}', $target_date->toDateString(), $description);
    }
}
