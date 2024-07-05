<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Observers\UserObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[ObservedBy(UserObserver::class)]
class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

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
        ];
    }

    protected function isValid(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // if you have balance, of course you can use the service
                return $attributes['balance'] > 0
                    // or you have a github account created N years ago, N - 3 > abs(balance)
                    // e.g. balance = -1, github_created_at = 2019-01-01, now = 2024-01-01, then 2024 - 2019 - 3 = 2 > 1, so it's also valid
                    || Carbon::parse($attributes['github_created_at'])->diffInYears(now()) - 3 > abs($attributes['balance']);
            },
        );
    }

    protected function isLowPriority(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                return $attributes['balance'] < 0;
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
}
