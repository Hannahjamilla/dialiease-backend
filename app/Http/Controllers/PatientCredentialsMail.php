<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PatientCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $password;
    public $hospitalNumber;
    public $initialAppointment;
    public $followUpDate;

    public function __construct($email, $password, $hospitalNumber, $initialAppointment, $followUpDate)
    {
        $this->email = $email;
        $this->password = $password;
        $this->hospitalNumber = $hospitalNumber;
        $this->initialAppointment = $initialAppointment;
        $this->followUpDate = $followUpDate;
    }

    public function build()
    {
        return $this->subject('Your Patient Portal Credentials')
                   ->view('emails.patient_credentials')
                   ->with([
                       'email' => $this->email,
                       'password' => $this->password,
                       'hospitalNumber' => $this->hospitalNumber,
                       'initialAppointment' => $this->initialAppointment,
                       'followUpDate' => $this->followUpDate
                   ]);
    }
}