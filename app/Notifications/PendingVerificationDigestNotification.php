<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class PendingVerificationDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $sessions;

    /**
     * Create a new notification instance.
     */
    public function __construct(Collection $sessions)
    {
        $this->sessions = $sessions;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('แจ้งเตือนรวบยอด: มีงานตรวจสอบความสะอาดใหม่ ' . $this->sessions->count() . ' รายการ')
                    ->view('emails.inspections.digest', ['sessions' => $this->sessions]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'pending_verification_digest',
            'count' => $this->sessions->count(),
            'title' => '📋 สรุปงานรอตรวจสอบ ' . $this->sessions->count() . ' รายการ',
            'message' => 'มีงานตรวจสอบความสะอาดส่งเข้ามาใหม่ ' . $this->sessions->count() . ' รายการ รอให้คุณยืนยันผล',
            'url' => route('inspection.verification', ['tab' => 'pending']),
        ];
    }
}
