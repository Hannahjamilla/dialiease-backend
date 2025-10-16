<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Patient;
use App\Mail\CheckupReminderMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class SendCheckupReminders extends Command
{
    protected $signature = 'reminders:send';
    protected $description = 'Send check-up reminders to patients';

    public function handle()
    {
        // Get today's date
        $today = Carbon::today();
        
        // Send 3 sets of reminders for each time frame
        for ($i = 1; $i <= 3; $i++) {
            // Send 2-day advance reminders
            $advanceDate = $today->copy()->addDays(2);
            $this->sendReminders($advanceDate, 'advance', $i);
            
            // Send tomorrow's reminders
            $tomorrowDate = $today->copy()->addDay();
            $this->sendReminders($tomorrowDate, 'tomorrow', $i);
            
            // Send today's reminders
            $this->sendReminders($today, 'today', $i);
            
            $this->info("Batch {$i} of reminders sent successfully.");
            
            // Add delay between batches if not the last iteration
            if ($i < 3) {
                sleep(60); // Wait 1 minute between batches for testing
                // In production, you might want to use a longer delay (e.g., 3600 for 1 hour)
            }
        }
    }

    protected function sendBatchReminders(Carbon $targetDate, string $reminderType)
    {
        $appointments = Schedule::with(['patient.user'])
            ->whereDate('appointment_date', $targetDate)
            ->where('confirmation_status', 'confirmed')
            ->where('checkup_status', 'pending')
            ->get();
            
        foreach ($appointments as $appointment) {
            $user = $appointment->patient->user;
            $formattedDate = $appointment->appointment_date->format('F j, Y');
            
            Mail::to($user->email)->send(
                new CheckupReminderMail(
                    $user->full_name,
                    $formattedDate,
                    $reminderType,
                    $appointment->patient->hospitalNumber
                )
            );
            
            $this->info("Sent {$reminderType} reminder to {$user->email} for {$formattedDate}");
        }
    }
    
    protected function sendReminders($targetDate, $reminderType, $batchNumber)
    {
        // Get confirmed appointments for the target date
        $appointments = Schedule::with(['patient.user'])
            ->whereDate('appointment_date', $targetDate)
            ->where('confirmation_status', 'confirmed')
            ->where('checkup_status', 'pending')
            ->get();
            
        foreach ($appointments as $appointment) {
            $user = $appointment->patient->user;
            $patient = $appointment->patient;
            
            // Format the appointment date without time
            $formattedDate = Carbon::parse($appointment->appointment_date)
                ->format('F j, Y');
            
            // Send email reminder
            Mail::to($user->email)->send(
                new CheckupReminderMail(
                    $user->first_name . ' ' . $user->last_name,
                    $formattedDate,
                    $reminderType,
                    $patient->hospitalNumber
                )
            );
            
            // Log the sent reminder
            $this->info("Sent {$reminderType} reminder (batch {$batchNumber}) to {$user->email} for appointment on {$formattedDate}");
        }
    }
} 