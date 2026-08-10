<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\InspectionSession;

class PendingVerificationNotification extends Notification
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
        $deptName = $this->session->department ? $this->session->department->dept_name : 'ไม่ระบุแผนก';
        $shiftMap = ['morning' => 'เช้า', 'afternoon' => 'บ่าย', 'night' => 'ดึก'];
        $shift = $shiftMap[$this->session->shift] ?? ucfirst($this->session->shift);

        return [
            'type' => 'pending_verification',
            'session_id' => $this->session->id,
            'title' => "รอทวนสอบ: {$deptName} (กะ{$shift})",
            'message' => 'พนักงานตรวจเสร็จสิ้นแล้ว รอการทวนสอบผล',
            'url' => route('inspection.verification'),
            'icon' => 'bi-shield-check'
        ];
    }
}
