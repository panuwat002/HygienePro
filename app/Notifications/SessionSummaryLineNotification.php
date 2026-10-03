<?php

namespace App\Notifications;

use App\Models\InspectionSession;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Channels\LineMessagingChannel;

class SessionSummaryLineNotification extends Notification
{
    use Queueable;

    public $session;
    public $stats;
    public $randomAssigned;

    public function __construct(InspectionSession $session, array $stats, int $randomAssigned)
    {
        $this->session = $session;
        $this->stats = $stats;
        $this->randomAssigned = $randomAssigned;
    }

    public function via($notifiable)
    {
        return [LineMessagingChannel::class];
    }

    public function toLine($notifiable)
    {
        $deptName = $this->session->department->dept_name ?? 'ไม่ระบุแผนก';
        $shiftLabel = $this->session->shift_label ?? $this->session->shift;
        
        $type = $this->session->type ?? 'personnel';
        $targetColumn = 'employee_id';
        $unit = 'คน';
        if ($type === 'machine') {
            $targetColumn = 'machine_id';
            $unit = 'เครื่อง';
        } elseif ($type === 'area') {
            $targetColumn = 'location_id';
            $unit = 'พื้นที่';
        }

        $totalTargets = $this->session->logs()->distinct($targetColumn)->count($targetColumn);
        $failedTargets = $this->session->logs()->where('result', 'fail')->distinct($targetColumn)->count($targetColumn);
        $passedTargets = $totalTargets - $failedTargets;
        
        $randomTargets = $this->session->logs()->whereNull('verification_status')->distinct($targetColumn)->count($targetColumn);
        
        $message = "📊 [สรุปผลการตรวจสุขลักษณะ]\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "🏭 แผนก: {$deptName}\n";
        $message .= "⏱ กะการทำงาน: {$shiftLabel}\n\n";
        
        $message .= "✨ สรุปผลลัพธ์:\n";
        $message .= "✅ ตรวจผ่าน: {$passedTargets} {$unit}\n";
        
        if ($failedTargets > 0) {
            $message .= "❌ พบปัญหา: {$failedTargets} {$unit} ({$this->stats['fail']} จุด)\n";
            $message .= $this->failureList();
        } else {
            $message .= "❌ พบปัญหา (CAR): 0 {$unit}\n";
        }

        $message .= "━━━━━━━━━━━━━━━━━━\n";
        
        $url = route('inspection.verification');
        $message .= "🔍 ขอเชิญ QA Supervisor ทวนสอบข้อมูล:\n";
        $message .= "👉 คลิกที่นี่: {$url}";

        return $message;
    }

    /**
     * How many targets a single message will name before summarising the rest.
     *
     * A round of 115 people with a bad day would otherwise run past LINE's
     * 5000-character message limit and be refused outright - losing the whole
     * summary, not just the tail of the list.
     */
    public const MAX_LISTED = 10;

    /**
     * Who or what failed, and on which check.
     *
     * This list used to be a separate LINE push per failing employee, sent
     * from InspectionController::storeLog the moment each was recorded. The
     * channel's allowance is 300 messages a month and the rest of the system
     * already spends about 130 of it, so a round where twenty people failed
     * could take out a sixth of the month in one shift - and the channel logs
     * and swallows a refusal, so the alerts would simply stop arriving with
     * nothing on screen to say so. Exactly when hygiene is worst.
     *
     * One message per round, whatever the result, is a cost that does not move.
     */
    private function failureList(): string
    {
        $failed = $this->session->logs()
            ->where('result', 'fail')
            ->with(['employee', 'machine', 'location', 'checkpoint'])
            ->get()
            ->groupBy(fn ($log) => $this->targetName($log));

        if ($failed->isEmpty()) {
            return '';
        }

        $text = "\n📋 รายการที่ไม่ผ่าน:\n";

        foreach ($failed->take(self::MAX_LISTED) as $name => $logs) {
            $checks = $logs
                ->map(fn ($log) => $log->checkpoint?->title ?? $log->checkpoint_title_snapshot ?? 'ไม่ระบุจุดตรวจ')
                ->unique()
                ->implode(', ');

            $text .= "❌ {$name} — {$checks}\n";
        }

        if ($failed->count() > self::MAX_LISTED) {
            $text .= '… และอีก ' . ($failed->count() - self::MAX_LISTED) . " รายการ\n";
        }

        return $text;
    }

    private function targetName($log): string
    {
        return $log->employee?->fullname
            ?? $log->machine?->name
            ?? $log->location?->location_name
            ?? 'ไม่ระบุเป้าหมาย';
    }
}
