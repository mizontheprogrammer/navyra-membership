<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class MembershipApplicationSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public MembershipApplication $application)
    {
        $this->afterCommit();
        $this->onQueue('membership-mail');
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('membership.verify', now()->addHours(48), ['application' => $this->application->id, 'hash' => sha1($this->application->email)]);

        return (new MailMessage)->subject('Membership application received')
            ->greeting('Hello '.$this->application->full_name.',')
            ->line('We received your membership application.')
            ->line('Reference number: '.$this->application->reference_no)
            ->line('Status: Pending verification')
            ->action('Confirm my email address', $url)
            ->line('This confirmation link expires in 48 hours. Confirming your email does not approve membership or record a payment.')
            ->line('Keep this reference number for your records.')
            ->salutation('Navyra Membership');
    }
}
