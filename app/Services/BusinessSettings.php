<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BusinessSettings
{
    private ?array $values = null;

    public const DEFINITIONS = [
        'usd_rmb_rate' => ['config' => 'yap.payment.usd_rmb_rate', 'label' => 'USD to CNY exchange rate', 'decimals' => 6, 'positive' => true],
        'usd_jpy_rate' => ['config' => 'yap.payment.usd_jpy_rate', 'label' => 'USD to JPY exchange rate', 'decimals' => 6, 'positive' => true],
        'unit_price' => ['config' => 'yap.unit_price', 'label' => 'Traffic price (USD / GiB)', 'decimals' => 2, 'positive' => false],
        'reset_subscription_price' => ['config' => 'yap.reset_subscription_price', 'label' => 'Subscription reset price (USD)', 'decimals' => 2, 'positive' => false],
        'balance_reminder_threshold' => ['config' => 'yap.balance_reminder_threshold', 'label' => 'Low balance reminder threshold (USD)', 'decimals' => 2, 'positive' => false],
    ];

    public static function rules(): array
    {
        $rules = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $decimals = $definition['decimals'];
            $rules[$key] = ['required', 'numeric', 'regex:/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,'.$decimals.'})?\z/', $definition['positive'] ? 'gt:0' : 'min:0'];
        }

        return $rules;
    }

    public function all(): array
    {
        if ($this->values === null) {
            $persisted = Setting::withTrashed()->whereIn('key', array_keys(self::DEFINITIONS))->pluck('value', 'key')->all();
            $values = [];
            foreach (self::DEFINITIONS as $key => $definition) {
                $values[$key] = array_key_exists($key, $persisted) ? $persisted[$key] : (string) config($definition['config']);
            }
            $this->values = Validator::make($values, self::rules())->validate();
        }

        return $this->values;
    }

    public function get(string $key): string
    {
        throw_if(! array_key_exists($key, self::DEFINITIONS), \InvalidArgumentException::class, 'Unknown business setting.');

        return $this->all()[$key];
    }

    public function reset(): void
    {
        $this->values = null;
    }

    public function initialize(): void
    {
        DB::transaction(function (): void {
            foreach ($this->all() as $key => $value) {
                $setting = Setting::withTrashed()->firstOrCreate(['key' => $key], ['value' => $value]);
                if ($setting->wasRecentlyCreated) {
                    $this->recordChange($key, null, $value, null);
                }
            }
        });
        $this->reset();
    }

    public function save(array $values, User $user): void
    {
        abort_unless($user->id === 1, 403);
        $values = Validator::make($values, self::rules())->validate();
        DB::transaction(function () use ($values, $user): void {
            foreach ($values as $key => $value) {
                $setting = Setting::withTrashed()->firstOrCreate(['key' => $key], ['value' => (string) config(self::DEFINITIONS[$key]['config'])]);
                $setting = Setting::withTrashed()->whereKey($setting->id)->lockForUpdate()->firstOrFail();
                $old_value = $setting->value;
                $setting->value = (string) $value;
                $setting->deleted_at = null;
                $setting->save();
                if ($old_value !== (string) $value) {
                    $this->recordChange($key, $old_value, (string) $value, $user->id);
                }
            }
            DB::afterCommit(fn () => app(AdminDashboardReportService::class)->clearDashboardCache());
        });
        $this->reset();
    }

    private function recordChange(string $key, ?string $old_value, string $new_value, ?int $user_id): void
    {
        DB::table('setting_changes')->insert([
            'key' => $key, 'old_value' => $old_value, 'new_value' => $new_value,
            'user_id' => $user_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
