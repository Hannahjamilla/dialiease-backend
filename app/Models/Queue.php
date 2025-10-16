<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Queue extends Model
{
    use HasFactory; // Remove SoftDeletes

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'queue';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'queue_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'userID',
        'queue_number',
        'appointment_date',
        'status',
        'checkup_status',
        'emergency_status',
        'emergency_priority',
        'start_time',
        'doctor_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'appointment_date' => 'date',
        'start_time' => 'datetime',
        'emergency_status' => 'boolean',
        'emergency_priority' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        // Remove deleted_at from casts
    ];

    /**
     * Remove deleted_at from hidden attributes
     */
    protected $hidden = [
        // Remove deleted_at
    ];

    /**
     * Default attribute values.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'waiting',
        'checkup_status' => 'Pending',
        'emergency_status' => false,
        'emergency_priority' => 0,
    ];

    /**
     * Get the patient user associated with this queue.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userID', 'userID');
    }

    /**
     * Get the patient record associated with this queue through the user.
     */
    public function patient()
    {
        return $this->hasOneThrough(
            Patient::class,
            User::class,
            'userID', // Foreign key on users table
            'userID', // Foreign key on patients table
            'userID', // Local key on queue table
            'userID'  // Local key on users table
        );
    }

    /**
     * Get the doctor assigned to this queue.
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id', 'userID')
                    ->where('userLevel', 'doctor');
    }

    /**
     * Scope a query to only include today's queues.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }

    /**
     * Scope a query to only include waiting queues.
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope a query to only include in-progress queues.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in-progress');
    }

    /**
     * Scope a query to only include completed queues.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include emergency queues.
     */
    public function scopeEmergency($query)
    {
        return $query->where('emergency_status', true);
    }

    /**
     * Scope a query to order by emergency priority (highest first).
     */
    public function scopeOrderByEmergencyPriority($query)
    {
        return $query->orderBy('emergency_priority', 'desc');
    }

    /**
     * Scope a query to order by queue number (ascending).
     */
    public function scopeOrderByQueueNumber($query)
    {
        return $query->orderBy('queue_number', 'asc');
    }

    /**
     * Check if the queue is waiting.
     */
    public function isWaiting()
    {
        return $this->status === 'waiting';
    }

    /**
     * Check if the queue is in progress.
     */
    public function isInProgress()
    {
        return $this->status === 'in-progress';
    }

    /**
     * Check if the queue is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the queue is an emergency case.
     */
    public function isEmergency()
    {
        return (bool) $this->emergency_status;
    }

    /**
     * Get the next available queue number for a given date.
     */
    public static function getNextQueueNumber($date)
    {
        $lastQueue = self::whereDate('appointment_date', $date)
                        ->orderBy('queue_number', 'desc')
                        ->first();

        return $lastQueue ? $lastQueue->queue_number + 1 : 1;
    }

    /**
     * Mark the queue as in progress and set start time.
     */
    public function markAsInProgress($doctorId = null)
    {
        $this->status = 'in-progress';
        $this->start_time = now();
        
        if ($doctorId) {
            $this->doctor_id = $doctorId;
        }
        
        return $this->save();
    }

    /**
     * Mark the queue as completed.
     */
    public function markAsCompleted()
    {
        $this->status = 'completed';
        $this->checkup_status = 'Completed';
        
        return $this->save();
    }

    /**
     * Mark the queue as cancelled.
     */
    public function markAsCancelled()
    {
        $this->status = 'cancelled';
        
        return $this->save();
    }

    /**
     * Get queues for a specific doctor.
     */
    public static function getDoctorQueues($doctorId, $date = null)
    {
        $query = self::where('doctor_id', $doctorId);
        
        if ($date) {
            $query->whereDate('appointment_date', $date);
        }
        
        return $query->orderBy('queue_number')->get();
    }

    /**
     * Get today's queues with patient and doctor information.
     */
    public static function getTodayQueuesWithRelations()
    {
        return self::today()
                ->with(['user', 'patient', 'doctor'])
                ->orderBy('emergency_status', 'desc')
                ->orderBy('emergency_priority', 'desc')
                ->orderBy('queue_number', 'asc')
                ->get();
    }

    /**
     * Get the formatted start time.
     */
    public function getFormattedStartTimeAttribute()
    {
        return $this->start_time ? $this->start_time->format('h:i A') : 'N/A';
    }

    /**
     * Get the formatted appointment date.
     */
    public function getFormattedAppointmentDateAttribute()
    {
        return $this->appointment_date->format('F j, Y');
    }

    /**
     * Get the patient's full name through the user relationship.
     */
    public function getPatientNameAttribute()
    {
        if ($this->user) {
            return $this->user->first_name . ' ' . $this->user->last_name;
        }

        return 'Unknown Patient';
    }

    /**
     * Get the doctor's full name.
     */
    public function getDoctorNameAttribute()
    {
        if ($this->doctor) {
            return 'Dr. ' . $this->doctor->first_name . ' ' . $this->doctor->last_name;
        }

        return 'Not Assigned';
    }

    /**
     * Boot function for model events.
     */
    protected static function boot()
    {
        parent::boot();

        // Auto-set appointment_date to today if not provided
        static::creating(function ($queue) {
            if (empty($queue->appointment_date)) {
                $queue->appointment_date = today();
            }
        });

        // Validate that doctor_id references a doctor
        static::saving(function ($queue) {
            if ($queue->doctor_id && !User::where('userID', $queue->doctor_id)->where('userLevel', 'doctor')->exists()) {
                throw new \Exception("The assigned user is not a doctor.");
            }
        });
    }
}