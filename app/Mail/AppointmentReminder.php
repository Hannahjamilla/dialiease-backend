<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentReminder extends Mailable
{
    use Queueable, SerializesModels;

    public $patientName;
    public $appointmentDate;
    public $isToday;

    public function __construct($patientName, $appointmentDate, $isToday = false)
    {
        $this->patientName = $patientName;
        $this->appointmentDate = $appointmentDate;
        $this->isToday = $isToday;
    }

    public function build()
    {
        return $this->subject($this->isToday ? 
            'URGENT: Your Appointment is TODAY - ' . $this->patientName : 
            'Reminder: Upcoming Appointment - ' . $this->patientName
        )->view('emails.appointment-reminder')
        ->with([
            'patientName' => $this->patientName,
            'appointmentDate' => $this->appointmentDate,
            'isToday' => $this->isToday,
        ]);
    }
}