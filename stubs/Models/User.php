<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'date_of_birth',
        'civility',
        'profession',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * Get the user's full name
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $firstInitial = $this->first_name ? Str::substr($this->first_name, 0, 1) : '';
        $lastInitial = $this->last_name ? Str::substr($this->last_name, 0, 1) : '';

        return strtoupper($firstInitial.$lastInitial);
    }

    /**
     * Get the user's display name (first name + last name initial)
     */
    public function getDisplayNameAttribute(): string
    {
        if (! $this->first_name) {
            return $this->last_name ?? '';
        }

        if (! $this->last_name) {
            return $this->first_name;
        }

        return $this->first_name.' '.Str::substr($this->last_name, 0, 1).'.';
    }
}
