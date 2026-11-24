<?php

namespace App\Notifications;

use App\Models\MembershipTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipTransactionCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public MembershipTransaction $transaction)
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
        $t = $this->transaction;

        return (new MailMessage)->subject('Membership '.$t->type.' received')
            ->greeting('Hello '.$t->member_name.',')->line('Your membership '.$t->type.' transaction was created.')
            ->line('Reference: '.$t->reference_number)->line('Category: '.$t->category_name)->line('Level: '.$t->level_name)
            ->line('Fee: PHP '.number_format((float) $t->amount, 2))->line('This is an acknowledgement of creation, not proof of payment, approval, or email ownership.')
            ->salutation('Navyra Membership');
    }
}
