<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use App\SerializeDate;
use Carbon\Carbon;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ObservedBy(UserObserver::class)]
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SerializeDate, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'uuid',
        'github_id',
        'github_nickname',
        'github_token',
        'github_created_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'github_token',
        'sub2api_key_id',
        'sub2api_last_usage_id',
        'sub2api_last_synced_at',
    ];

    protected $appends = ['is_valid', 'is_low_priority'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_settled_at' => 'datetime',
            'github_created_at' => 'datetime',
            'password' => 'hashed',
            'balance' => 'decimal:2',
            'sub2api_last_synced_at' => 'datetime',
        ];
    }

    protected function isValid(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $balance = (float) ($attributes['balance'] ?? 0);
                $github_created_at = $attributes['github_created_at'] ?? null;

                // if you have balance, of course you can use the service
                return $balance > 0
                    // or if you have any active packages, you can also use the service
                    || $this->packages()->where('status', UserPackage::STATUS_ACTIVE)->exists()
                    // or you have a github account created N years ago, N - 9 > abs(balance)
                    || ($github_created_at !== null && Carbon::parse($github_created_at)->diffInYears(now()) - 9 > abs($balance));
            },
        );
    }

    protected function isLowPriority(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return (float) ($attributes['balance'] ?? 0) <= 0;
            },
        );
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function stats()
    {
        return $this->hasMany(UserStat::class);
    }

    public function balanceDetails()
    {
        return $this->hasMany(BalanceDetail::class);
    }

    public function packages()
    {
        return $this->hasMany(UserPackage::class);
    }

    public function sub2apiUsageRecords()
    {
        return $this->hasMany(Sub2apiUsageRecord::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->id === 1;
    }
}
