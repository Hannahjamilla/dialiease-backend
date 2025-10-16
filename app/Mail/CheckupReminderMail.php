<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CheckupReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $patientName;
    public $appointmentDate;
    public $reminderType;
    public $hospitalNumber;

    public function __construct($patientName, $appointmentDate, $reminderType, $hospitalNumber)
    {
        $this->patientName = $patientName;
        $this->appointmentDate = $appointmentDate;
        $this->reminderType = $reminderType;
        $this->hospitalNumber = $hospitalNumber;
    }

    public function build()
    {
        $subject = [
            'advance' => 'Reminder: Upcoming Check-up in 2 Days',
            'tomorrow' => 'Reminder: Your Check-up is Tomorrow',
            'today' => 'Reminder: Your Check-up is Today',
        ][$this->reminderType] ?? 'Check-up Reminder';

        return $this->subject($subject)
                   ->view('emails.checkup_reminder');
    }
}