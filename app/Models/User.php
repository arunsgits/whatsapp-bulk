<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'parent_id',
        'profileImage',
        'number',
        'status',
        'wallet_enabled',
        'wallet_balance',
        'bio',
        'companyName',
        'companyLogo',
        'companyAddress',
        'companyEmail',
        'companyMobile',
        'resetPasswordToken',
        'resetPasswordExpires',
        'permissions',
        'temp_password',
        'fcm_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'resetPasswordToken',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'resetPasswordExpires' => 'datetime',
        'permissions' => 'array',
        'wallet_enabled' => 'boolean',
        'wallet_balance' => 'decimal:2',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted()
    {
        static::updated(function ($user) {
            // When an admin / company user updates their companyName, automatically update companyName for all staff members under them
            if ($user->wasChanged('companyName') && !empty($user->companyName)) {
                static::where('parent_id', $user->id)
                    ->update(['companyName' => $user->companyName]);
            }
        });
    }

    /**
     * Get the contacts for the user.
     */
    public function contacts()
    {
        return $this->hasMany(Contact::class, 'userId');
    }

    /**
     * Get the groups for the user.
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'userId');
    }

    /**
     * Get the bulk messages for the user.
     */
    public function bulkMessages()
    {
        return $this->hasMany(BulkMessage::class, 'userId');
    }

    /**
     * Get the active package for the user.
     */
    public function activePackage()
    {
        return $this->hasOne(ActivePackage::class, 'userId')
            ->whereIn('status', [ActivePackage::STATUS_ACTIVE, ActivePackage::STATUS_UPCOMING])
            ->latest('id');
    }

    /**
     * Get package assignment histories for the user.
     */
    public function packageHistories()
    {
        return $this->hasMany(UserPackageHistory::class, 'userId')->orderBy('created_at', 'desc');
    }

    /**
     * Get the company/admin user this staff member belongs to.
     */
    public function companyUser()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    /**
     * Get staff members belonging to this company/admin user.
     */
    public function staffMembers()
    {
        return $this->hasMany(User::class, 'parent_id');
    }

    /**
     * Get the profile image URL with domain from APP_URL
     */
    public function getProfileImageUrlAttribute()
    {
        if ($this->profileImage) {
            // For production server, ensure /public/ is in the path
            $imagePath = $this->profileImage;
            $isProduction = app()->isProduction();
            if ($isProduction && !str_contains($imagePath, '/public/')) {
                $imagePath = '/public' . $imagePath;
            }
            return rtrim(config('app.url'), '/') . '/' . ltrim($imagePath, '/');
        }

        // Return default avatar URL
        $isProduction = app()->isProduction();

        $basePath = $isProduction ? '/public/images/' : '/images/';
        return rtrim(config('app.url'), '/') . $basePath . 'default-avatar.svg';
    }

    /**
     * Get the company logo URL with domain from APP_URL
     */
    public function getCompanyLogoUrlAttribute()
    {
        if ($this->companyLogo) {
            // For production server, ensure /public/ is in the path
            $imagePath = $this->companyLogo;
            $isProduction = app()->isProduction();
            if ($isProduction && !str_contains($imagePath, '/public/')) {
                $imagePath = '/public' . $imagePath;
            }
            return rtrim(config('app.url'), '/') . '/' . ltrim($imagePath, '/');
        }

        // Return default company logo URL
        $isProduction = app()->isProduction();
        $basePath = $isProduction ? '/public/images/' : '/images/';
        return rtrim(config('app.url'), '/') . $basePath . 'default-company-logo.svg';
    }

    /**
     * Get first name dynamically from existing name column
     */
    public function getFirstNameAttribute(): string
    {
        if (!empty($this->attributes['name'])) {
            $parts = explode(' ', trim($this->attributes['name']));
            return $parts[0] ?? '';
        }
        return '';
    }

    /**
     * Get last name dynamically from existing name column
     */
    public function getLastNameAttribute(): string
    {
        if (!empty($this->attributes['name'])) {
            $parts = explode(' ', trim($this->attributes['name']));
            return count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
        }
        return '';
    }

    /**
     * Check if user is an admin or super admin
     */
    public function isAdmin(): bool
    {
        return in_array(strtolower($this->role ?? ''), ['admin', 'super_admin', 'super administrator', 'administrator']);
    }

    /**
     * Check if user is super admin
     */
    public function isSuperAdmin(): bool
    {
        return in_array(strtolower($this->role ?? ''), ['super_admin', 'super administrator', 'superadmin']);
    }

    /**
     * Check if user has specific permission module access
     */
    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $perms = $this->permissions;
        if (is_string($perms)) {
            $perms = json_decode($perms, true);
        }

        if (is_array($perms) && !empty($perms)) {
            $lowerKey = strtolower($permissionKey);

            // 1. Associative map format: {"catalog": 0, "contacts": 1} or {"catalog": false}
            if (array_key_exists($permissionKey, $perms)) {
                return !empty($perms[$permissionKey]) && $perms[$permissionKey] != '0';
            }
            if (array_key_exists($lowerKey, $perms)) {
                return !empty($perms[$lowerKey]) && $perms[$lowerKey] != '0';
            }

            // 2. Wildcard access
            if (in_array('*', $perms, true)) {
                return true;
            }

            // 3. Flat list format: ["contacts", "campaigns", "settings"]
            foreach ($perms as $p) {
                if (is_string($p) && strtolower($p) === $lowerKey) {
                    return true;
                }
            }

            return false;
        }

        // Backward compatibility: If an admin has no permissions set, grant full access
        if (strtolower($this->role ?? '') === 'admin') {
            return true;
        }

        // Default staff permissions if none explicitly configured
        if (strtolower($this->role ?? '') === 'staff') {
            $defaultStaff = ['contacts', 'campaigns', 'templates', 'conversations', 'reports', 'settings', 'billing'];
            return in_array(strtolower($permissionKey), $defaultStaff, true);
        }

        return true;
    }

    /**
     * Get parent company user ID for data scoping
     */
    public function getCompanyId(): int
    {
        if (strtolower($this->role ?? '') === 'staff' && !empty($this->parent_id)) {
            return (int) $this->parent_id;
        }
        return (int) $this->id;
    }
}
