<?php

namespace App\Notifications;

use App\Models\InspectionSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to department managers ONCE, when an inspection session finishes and it
 * produced one or more auto-CARs. Replaces the stream of per-CAR notifications
 * that used to fire during the session — those swamped the notification bell
 * during a normal shift with 10-30 fails.
 *
 * Manual escalations via CorrectiveActionController still fire their own
 * per-CAR notification, because those are explicit high-priority actions.
 */
class SessionCarsSummaryNotification extends Notification
{
    use Queueable;

    public InspectionSession $session;
    public int $carsCount;
    public array $carsPreview;

    public function __construct(InspectionSession $session, array $carSummaries)
    {
        $this->session = $session;
        $this->carsCount = count($carSummaries);
        // Keep only the first few for the compact bell payload; the full list is
        // available on the CAR page which the link points to.
        $this->carsPreview = array_slice($carSummaries, 0, 5);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $deptName = $this->session->department->dept_name ?? 'แผนก';
        // The ternary this replaced knew two shifts and printed the raw column
        // ("custom_11") for every other one.
        $shiftLabel = $this->session->shift_label;
        $sample = collect($this->carsPreview)->pluck('place')->filter()->take(3)->implode(', ');

        return [
            'type' => 'session_cars_summary',
            'session_id' => $this->session->id,
            'title' => "สรุปใบสั่งซ่อมจาก session ({$this->carsCount} รายการ)",
            'message' => "รอบตรวจ {$deptName} {$shiftLabel} สร้าง {$this->carsCount} CARs" . ($sample !== '' ? " — {$sample}" : ''),
            'url' => route('corrective.index'),
            'icon' => 'bi-list-check',
            'cars_count' => $this->carsCount,
            'cars_preview' => $this->carsPreview,
        ];
    }
}
