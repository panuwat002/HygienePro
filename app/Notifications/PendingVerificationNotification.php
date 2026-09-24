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
        // shift_label resolves custom_<id> against the shifts table and already
        // carries its own "กะ" prefix. The map this replaced knew only the three
        // hardcoded shifts and ucfirst()ed everything else into "Custom_11".
        $shift = $this->session->shift_label;

        return [
            'type' => 'pending_verification',
            'session_id' => $this->session->id,
            'title' => "รอทวนสอบ: {$deptName} ({$shift})",
            'message' => 'พนักงานตรวจเสร็จสิ้นแล้ว รอการทวนสอบผล',
            'url' => route('inspection.verification'),
            'icon' => 'bi-shield-check'
        ];
    }
}
