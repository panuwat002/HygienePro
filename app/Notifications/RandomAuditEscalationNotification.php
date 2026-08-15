<?php

namespace App\Notifications;

use App\Models\InspectionSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RandomAuditEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $session;
    public $recheckSession;
    public $failRate;
    public $failedEmployeesCount;
    public $totalInspected;

    /**
     * Create a new notification instance.
     */
    public function __construct(InspectionSession $session, InspectionSession $recheckSession, float $failRate, int $failedEmployeesCount, int $totalInspected)
    {
        $this->session = $session;
        $this->recheckSession = $recheckSession;
        $this->failRate = $failRate;
        $this->failedEmployeesCount = $failedEmployeesCount;
        $this->totalInspected = $totalInspected;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // We send the email using Mailable directly, so just DB here
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'random_audit_escalation',
            'session_id' => $this->session->id,
            'recheck_session_id' => $this->recheckSession->id,
            'department_name' => $this->session->department->dept_name ?? 'N/A',
            'fail_rate' => $this->failRate,
            'failed_employees_count' => $this->failedEmployeesCount,
            'total_inspected' => $this->totalInspected,
            'title' => 'สุ่มตรวจไม่ผ่านเกณฑ์ (' . number_format($this->failRate, 1) . '%)',
            'message' => 'แผนก ' . ($this->session->department->dept_name ?? 'N/A') . ' พบอัตราไม่ผ่านเกณฑ์สูง ระบบได้สร้าง Re-check (รอบใหม่) อัตโนมัติแล้ว',
        ];
    }
}
