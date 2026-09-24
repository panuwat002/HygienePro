<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\InspectionSession;

class VerificationEscalatedNotification extends Notification implements ShouldQueue
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
            'type' => 'verification_escalated',
            'session_id' => $this->session->id,
            'title' => '🚨 ด่วน: งานทวนสอบค้างเกิน 24 ชั่วโมง',
            // shift_label, not the raw column: that one holds keys like
            // "custom_11" and carries its own "กะ" prefix once resolved.
            'message' => 'รายงานงานตรวจ' . $this->session->shift_label . ' แผนก ' . ($this->session->department->dept_name ?? 'N/A') . ' ถูกทิ้งร้างและยังไม่ได้รับการยืนยันจาก Supervisor นานกว่า 24 ชั่วโมงแล้ว กรุณาตรวจสอบหรือติดตามผลด่วน',
            'url' => route('inspection.verification', [
                'filter_type' => $this->session->verificationFilterType(),
                'date' => optional($this->session->inspection_date)->format('Y-m-d'),
                'tab' => 'pending',
            ]),
        ];
    }
}
