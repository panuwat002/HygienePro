<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Channels\LineMessagingChannel;
use App\Models\CorrectiveAction;
use App\Models\InspectionLog;

class DailyDigestLineNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return [LineMessagingChannel::class];
    }

    public function toLine($notifiable)
    {
        $overdueCars = CorrectiveAction::where('status', 'open')
            ->where('due_date', '<', now())
            ->count();
            
        $openCars = CorrectiveAction::where('status', 'open')->count();
        
        $pendingVerifications = InspectionLog::where('verify_status', 'pending')->count();
        
        $message = "🌤️ 📊 [สรุปงานค้างประจำวัน HygienePro]\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "อรุณสวัสดิ์ครับ! วันนี้เรามีสถานะงานที่ต้องติดตามดังนี้:\n\n";
        
        $message .= "🔥 งานด่วนที่ต้องรีบจัดการ:\n";
        $message .= "🚨 CAR ที่เลยกำหนด (Overdue): {$overdueCars} ใบ\n\n";
        
        $message .= "📋 งานที่อยู่ระหว่างดำเนินการ:\n";
        $message .= "⚠️ CAR ที่ยังเปิดอยู่: {$openCars} ใบ\n";
        $message .= "⏳ เป้าหมายรอทวนสอบ: {$pendingVerifications} รายการ\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        
        $message .= "💪 ลุยงานกันต่อครับทุกคน!\n";
        $message .= "👉 เข้าสู่ระบบ: " . config('app.url');

        return $message;
    }
}
