<?php
// app/Mail/CheckupCompleted.php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;

class CheckupCompleted extends Mailable
{
    use Queueable, SerializesModels;

    public $patientName;
    public $completedDate;
    public $nextAppointmentDate;
    public $prescriptionDetails;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($patientName, $completedDate, $nextAppointmentDate, $prescriptionDetails = null)
    {
        $this->patientName = $patientName;
        $this->completedDate = $completedDate;
        $this->nextAppointmentDate = $nextAppointmentDate;
        $this->prescriptionDetails = $prescriptionDetails;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your CAPD Checkup is Complete - Next Appointment Scheduled')
                    ->view('emails.checkup-completed')
                    ->with([
                        'patientName' => $this->patientName,
                        'completedDate' => Carbon::parse($this->completedDate)->format('F j, Y'),
                        'nextAppointmentDate' => Carbon::parse($this->nextAppointmentDate)->format('F j, Y'),
                        'prescriptionDetails' => $this->prescriptionDetails,
                    ]);
    }
}