<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentRescheduled extends Mailable
{
    use Queueable, SerializesModels;

    public $firstName;
    public $lastName;
    public $newDate;
    public $oldDate;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($firstName, $lastName, $newDate, $oldDate)
    {
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->newDate = $newDate;
        $this->oldDate = $oldDate;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your Appointment Has Been Rescheduled')
                    ->view('emails.appointment-rescheduled')
                    ->with([
                        'firstName' => $this->firstName,
                        'lastName' => $this->lastName,
                        'newDate' => $this->newDate,
                        'oldDate' => $this->oldDate,
                    ]);
    }
}