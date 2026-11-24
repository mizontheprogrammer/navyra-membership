<?php

namespace App\Listeners;

use App\Models\MembershipApplication;
use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Notifications\Events\NotificationSent;

class RecordMembershipNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel === 'mail' && $event->notification instanceof MembershipApplicationSubmitted) {
            MembershipApplication::whereKey($event->notification->application->id)->update(['verification_sent_at' => now()]);
        }
    }
}
