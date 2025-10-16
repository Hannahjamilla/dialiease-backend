<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class ProviderCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $password;
    public $pdfContent;

    public function __construct(User $user, $password, $pdfContent)
    {
        $this->user = $user;
        $this->password = $password;
        $this->pdfContent = $pdfContent;
    }

    public function build()
    {
        return $this->subject('Your CAPD Healthcare System Credentials')
            ->markdown('emails.provider_credentials')
            ->attachData($this->pdfContent, 'CAPD_Credentials_'.$this->user->employeeNumber.'.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}