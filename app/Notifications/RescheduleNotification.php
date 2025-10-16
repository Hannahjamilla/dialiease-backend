<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RescheduleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $user;
    protected $schedule;

    public function __construct(User $user, Schedule $schedule)
    {
        $this->user = $user;
        $this->schedule = $schedule;
    }

    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Reschedule Request')
            ->line('Patient ' . $this->user->first_name . ' ' . $this->user->last_name . ' has requested to reschedule their appointment.')
            ->line('Current Date: ' . $this->schedule->appointment_date)
            ->line('Requested Date: ' . $this->schedule->reschedule_requested_date)
            ->line('Reason: ' . ($this->schedule->reschedule_reason ?? 'No reason provided'))
            ->action('Review Request', url('/staff-dashboard'))
            ->line('Please review this request as soon as possible.');
    }

    public function toArray($notifiable)
    {
        return [
            'message' => 'Patient ' . $this->user->first_name . ' ' . $this->user->last_name . ' has requested to reschedule their appointment.',
            'url' => '/staff-dashboard'
        ];
    }
}