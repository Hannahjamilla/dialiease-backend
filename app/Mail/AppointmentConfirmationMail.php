<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $patientName;
    public $appointmentDate;
    public $hospitalNumber;

    public function __construct($patientName, $appointmentDate, $hospitalNumber)
    {
        $this->patientName = $patientName;
        $this->appointmentDate = $appointmentDate;
        $this->hospitalNumber = $hospitalNumber;
    }

    public function build()
    {
        return $this->subject('Your Appointment Confirmation')
                    ->view('emails.appointment_confirmation')
                    ->with([
                        'patientName' => $this->patientName,
                        'appointmentDate' => $this->appointmentDate,
                        'hospitalNumber' => $this->hospitalNumber
                    ]);
    }
}