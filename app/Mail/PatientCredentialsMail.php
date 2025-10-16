<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PatientCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public $password;
    public $hospitalNumber;
    public $initialAppointment;
    public $followUpDate;
    public $patientName;
    public $dateOfBirth;
    public $gender;
    public $phoneNumber;


    public function __construct(
        $email,
        $password,
        $hospitalNumber,
        $initialAppointment,
        $followUpDate,
        $patientName,
        $dateOfBirth,
        $gender,
        $phoneNumber,
  
    ) {
        $this->email = $email;
        $this->password = $password;
        $this->hospitalNumber = $hospitalNumber;
        $this->initialAppointment = $initialAppointment;
        $this->followUpDate = $followUpDate;
        $this->patientName = $patientName;
        $this->dateOfBirth = $dateOfBirth;
        $this->gender = $gender;
        $this->phoneNumber = $phoneNumber;
       
    }

    public function build()
    {
        $pdf = $this->generateCertificate();
        
        return $this->subject('Your Patient Portal Credentials and Registration Certificate')
                   ->view('emails.patient_credentials')
                   ->attachData($pdf->output(), 'Patient_Registration_Certificate.pdf', [
                       'mime' => 'application/pdf',
                   ]);
    }

   protected function generateCertificate()
{
    $initialDate = Carbon::parse($this->initialAppointment);
    $followUpDate = Carbon::parse($this->followUpDate);
    
    // Generate full one-year schedule (strictly every 28 days)
    $appointmentSchedule = [];
    $currentDate = $initialDate->copy();
    $endDate = $currentDate->copy()->addYear(); // One year duration
    
    $counter = 1;
    while ($currentDate <= $endDate) {
        $appointmentSchedule[] = [
            'number' => $counter++,
            'date' => $currentDate->format('F j, Y'),
            'days_since_first' => ($currentDate->diffInDays($initialDate)) . ' days'
        ];
        $currentDate->addDays(28); // Strict 28-day interval
    }

    return PDF::loadView('pdf.patient_info_pdf', [
        'patientName' => $this->patientName,
        'hospitalNumber' => $this->hospitalNumber,
        'dateOfBirth' => Carbon::parse($this->dateOfBirth)->format('F j, Y'),
        'gender' => $this->gender,
        'phoneNumber' => $this->phoneNumber,
        'email' => $this->email,
     
        'initialAppointment' => $initialDate->format('F j, Y'),
        'followUpAppointment' => $followUpDate->format('F j, Y'),
        'appointmentSchedule' => $appointmentSchedule,
        'generatedDate' => now()->format('F j, Y')
    ])->setPaper('a4', 'portrait');
}
}