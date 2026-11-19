<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Database\Factories\UserFactory;
use App\Modules\Identity\Models\Concerns\HasRolesAndPermissions;
use App\Modules\Identity\Notifications\StaffPasswordResetNotification;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRolesAndPermissions, HasUlids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function oauthAccounts(): HasMany
    {
        return $this->hasMany(OAuthAccount::class);
    }

    public function publisherGrants(): HasMany
    {
        return $this->hasMany(PublisherPermission::class);
    }

    public function directPermissionGrants(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && ! $this->trashed();
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(config('identity.staff.roles', ['publisher', 'administrator']));
    }

    public function isParticipant(): bool
    {
        return $this->hasRole('participant');
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    public function hasPasswordCredential(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /**
     * Readers never reset a password; staff use a frontend-hosted reset flow.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new StaffPasswordResetNotification((string) $token, $this->email));
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
