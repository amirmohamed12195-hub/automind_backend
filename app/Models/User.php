<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'avatar_path', 'locale', 'theme_mode',
        'units', 'country_code', 'city', 'latitude', 'longitude', 'currency',
        'maintenance_reminders_enabled', 'last_login_at', 'deletion_requested_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'maintenance_reminders_enabled' => 'boolean',
            'is_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** @return HasMany<Vehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /** @return HasOne<BillingAccount, $this> */
    public function billingAccount(): HasOne
    {
        return $this->hasOne(BillingAccount::class);
    }

    /** @return HasMany<UserEntitlement, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(UserEntitlement::class);
    }

    /** @return HasMany<CreditLedgerEntry, $this> */
    public function creditLedgerEntries(): HasMany
    {
        return $this->hasMany(CreditLedgerEntry::class);
    }
}
