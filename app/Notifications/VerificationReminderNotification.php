<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\InspectionSession;

class VerificationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $session;

    /**
     * Create a new notification instance.
     */
    public function __construct(InspectionSession $session)
    {
        $this->session = $session;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'verification_reminder',
            'session_id' => $this->session->id,
            'title' => '⏳ เตือน: งานทวนสอบค้างเกิน 4 ชั่วโมง',
            'message' => 'คุณมีงานตรวจกะ ' . $this->session->shift . ' แผนก ' . ($this->session->department->dept_name ?? 'N/A') . ' รอการยืนยันผลมานานกว่า 4 ชั่วโมงแล้ว กรุณาตรวจสอบ',
            'url' => route('inspection.verification.dashboard', $this->session->type),
        ];
    }
}
