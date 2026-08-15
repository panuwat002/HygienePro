<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Channels\LineMessagingChannel;

class InspectionFailedLineNotification extends Notification
{
    use Queueable;

    protected $targetName;
    protected $department;
    protected $failedItems;

    /**
     * Create a new notification instance.
     */
    public function __construct($targetName, $department, $failedItems)
    {
        $this->targetName = $targetName;
        $this->department = $department;
        $this->failedItems = $failedItems; // array of ['title' => '...', 'correction' => '...']
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable)
    {
        return [LineMessagingChannel::class];
    }

    /**
     * Get the LINE representation of the notification.
     */
    public function toLine($notifiable)
    {
        $message = "🚨 🔴 [แจ้งเตือน: พบสิ่งผิดปกติ (CAR)] 🔴 🚨\n";
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "👤 ผู้ถูกตรวจ / เป้าหมาย: {$this->targetName}\n";
        $message .= "🏭 แผนก/พื้นที่: {$this->department}\n\n";
        $message .= "📋 รายการที่ไม่ผ่านเกณฑ์:\n";
        
        foreach ($this->failedItems as $item) {
            $message .= "❌ " . $item['title'] . "\n";
            $message .= "   ↳ 🛠️ การจัดการเบื้องต้น: " . $item['correction'] . "\n";
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━\n";
        $message .= "⚠️ ระบบได้เปิดใบสั่งซ่อม (CAR) และส่งเรื่องให้ผู้ที่เกี่ยวข้องดำเนินการแก้ไขเรียบร้อยแล้ว!";
        
        return $message;
    }
}
