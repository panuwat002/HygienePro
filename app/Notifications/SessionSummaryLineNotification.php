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
        } else {
            $message .= "❌ พบปัญหา (CAR): 0 {$unit}\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        
        $url = route('inspection.verification');
        $message .= "🔍 ขอเชิญ QA Supervisor ทวนสอบข้อมูล:\n";
        $message .= "👉 คลิกที่นี่: {$url}";

        return $message;
    }
}
