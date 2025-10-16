<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'userID';
    protected $keyType = 'int';
    public $incrementing = true;

    protected $fillable = [
        'userID', //for all users
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'employeeNumber',
        'date_of_birth',
        'gender',
        'phone_number',
        'specialization',
        'profile_image',
        'EmpAddress',
        'EmpStatus',
        'reg_number',
        'status',
        'Doc_license',
        'TodaysStatus',
        'userLevel',
        'custom_user_level',
        'password',
        'reset_token',
        'reset_expires',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'reset_token',
    ];

    public function patient(): HasOne
    {
        return $this->hasOne(Patient::class, 'userID', 'userID');
    }

    public function isAdmin(): bool
    {
        return strtolower($this->userLevel ?? '') === 'admin';
    }

    public function isPatient(): bool
    {
        return strtolower($this->userLevel ?? '') === 'patient';
    }

    public function empStatus(): HasOne
    {
        return $this->hasOne(EmpStatus::class, 'userID', 'userID');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function schedules()
{
    return $this->hasMany(Schedule::class, 'userID');
}
}