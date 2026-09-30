<?php

namespace App\Services;

use App\Models\BalanceDetail;
use App\Models\Payment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CustomerSalesReportService
{
    public function monthly(int $months = 12): array
    {
        $months = max(1, min($months, 24));
        $start = CarbonImmutable::now()->startOfMonth()->subMonths($months - 1);
        $end = CarbonImmutable::now()->addMonth()->startOfMonth();
        $report = [];

        for ($month = $start; $month < $end; $month = $month->addMonth()) {
            $report[$month->format('Y-m')] = [
                'new_users' => 0,
                'first_time_buyers' => 0,
                'returning_buyers' => 0,
                'top_up' => 0.0,
                'balance_charges' => 0.0,
            ];
        }

        $users_period = $this->monthExpression('users.created_at');
        $registrations = User::query()
            ->where('users.id', '>', AdminDashboardReportService::REPORTABLE_USER_ID_THRESHOLD)
            ->where('users.created_at', '>=', $start)
            ->where('users.created_at', '<', $end)
            ->selectRaw("{$users_period} as period, COUNT(*) as total")
            ->groupByRaw($users_period)
            ->get();

        foreach ($registrations as $row) {
            if (isset($report[$row->period])) {
                $report[$row->period]['new_users'] = (int) $row->total;
            }
        }

        $first_payments = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('user_id', '>', AdminDashboardReportService::REPORTABLE_USER_ID_THRESHOLD)
            ->selectRaw('user_id, MIN(created_at) as first_paid_at')
            ->groupBy('user_id');
        $payments_period = $this->monthExpression('payments.created_at');
        $payments = Payment::query()
            ->joinSub($first_payments, 'first_payments', 'first_payments.user_id', '=', 'payments.user_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->where('payments.user_id', '>', AdminDashboardReportService::REPORTABLE_USER_ID_THRESHOLD)
            ->where('payments.created_at', '>=', $start)
            ->where('payments.created_at', '<', $end)
            ->selectRaw("{$payments_period} as period, payments.user_id, first_payments.first_paid_at")
            ->selectRaw('SUM(payments.amount) as total')
            ->groupByRaw("{$payments_period}, payments.user_id, first_payments.first_paid_at")
            ->get();

        foreach ($payments as $row) {
            $period = $row->period;
            $report[$period]['top_up'] = round($report[$period]['top_up'] + (float) $row->total, 2);
            $key = substr($row->first_paid_at, 0, 7) === $period ? 'first_time_buyers' : 'returning_buyers';
            $report[$period][$key]++;
        }

        $charges_period = $this->monthExpression('balance_details.created_at');
        $charges = BalanceDetail::query()
            ->where('user_id', '>', AdminDashboardReportService::REPORTABLE_USER_ID_THRESHOLD)
            ->where('amount', '<', 0)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw("{$charges_period} as period, ABS(SUM(amount)) as total")
            ->groupByRaw($charges_period)
            ->get();

        foreach ($charges as $row) {
            $report[$row->period]['balance_charges'] = round((float) $row->total, 2);
        }

        return $report;
    }

    private function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
