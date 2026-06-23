<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InspectionRejectedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    protected $session;
    protected $reason;

    /**
     * Create a new notification instance.
     */
    public function __construct($session, $reason)
    {
        $this->session = $session;
        $this->reason = $reason;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('❌ Inspection Rejected / ผลการตรวจสอบถูกตีกลับ')
            ->greeting('เรียนคุณ ' . $notifiable->name)
            ->line('ผลการตรวจสอบประจำรอบของคุณถูกตีกลับ (Rejected) โดยหัวหน้างาน')
            ->line('เหตุผล: ' . $this->reason)
            ->action('ตรวจสอบและแก้ไข', route('inspection.scan', $this->session->id))
            ->line('กรุณาดำเนินการแก้ไขโดยด่วน');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'งานถูกตีกลับ (Rejected)',
            'message' => 'รอบตรวจสอบของคุณถูก Reject: ' . $this->reason,
            'link' => route('inspection.scan', $this->session->id),
            'icon' => 'bi-x-circle-fill text-danger',
            'session_id' => $this->session->id
        ];
    }
}
