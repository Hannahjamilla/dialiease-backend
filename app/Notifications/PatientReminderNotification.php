<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PatientReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Reminder: Upcoming Checkup Appointment')
            ->line('You have a checkup appointment tomorrow. Please confirm your attendance.')
            ->action('Confirm Appointment', url('/patient-dashboard'))
            ->line('Thank you for using our service!');
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'Reminder: You have a checkup appointment tomorrow. Please confirm your attendance.',
            'url' => '/patient-dashboard'
        ];
    }
}