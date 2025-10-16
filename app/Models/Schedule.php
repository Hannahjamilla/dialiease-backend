<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Schedule extends Model
{
    use HasFactory;

    protected $primaryKey = 'schedule_id';
    protected $table = 'schedule';

    protected $fillable = [
        'queuing_ID',
        'userID',
        'patient_id',
        'appointment_date',
        'new_appointment_date',
        'reschedule_requested_date',
        'reschedule_reason',
        'reschedule_request_date',
        'confirmation_status',
        'checkup_status',
        'checkup_remarks',
        'missed_count',
        'remarks',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id', 'patientID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userID', 'userID');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'userID');
    }

    /**
     * Calculate the next appointment date (exactly 28 days later)
     */
    public static function calculateNextAppointmentDate($date)
    {
        return Carbon::parse($date)->addDays(28)->format('Y-m-d');
    }

    /**
     * Scope for active appointments
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('checkup_status', ['completed', 'cancelled']);
    }

    /**
     * Scope for upcoming appointments
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->format('Y-m-d'))
                    ->where('checkup_status', 'pending');
    }

    /**
     * Scope for missed appointments
     */
    public function scopeMissed($query)
    {
        return $query->where('appointment_date', '<', now()->format('Y-m-d'))
                    ->where('checkup_status', 'pending');
    }
}