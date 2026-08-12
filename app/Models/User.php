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
        'maintenance_reminders_enabled', 'last_login_at', 'deletion_requested_at', 'suspended_at', 'suspension_reason',
        'terms_accepted_at', 'terms_version', 'privacy_accepted_at', 'privacy_version', 'is_admin', 'admin_role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'suspended_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
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

    /** @return HasMany<DiagnosticSession, $this> */
    public function diagnostics(): HasMany
    {
        return $this->hasMany(DiagnosticSession::class);
    }

    /** @return HasMany<Appointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /** @return HasMany<UserNotification, $this> */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }
}
