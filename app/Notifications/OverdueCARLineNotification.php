<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Channels\LineMessagingChannel;

class OverdueCARLineNotification extends Notification
{
    use Queueable;

    public $overdueActions;

    public function __construct($overdueActions)
    {
        $this->overdueActions = $overdueActions;
    }

    public function via($notifiable)
    {
        return [LineMessagingChannel::class];
    }

    public function toLine($notifiable)
    {
        $count = $this->overdueActions->count();
        $message = "🚨 *แจ้งเตือนด่วน: ปัญหาค้างแก้ไขเกินกำหนด (Overdue CAR)* 🚨\n\n";
        $message .= "พบปัญหาที่ถูกสั่งให้แก้ไข แต่ยังไม่เสร็จสิ้นตามกำหนดเวลา (SLA) จำนวน *{$count}* รายการ:\n\n";

        // Display up to 5 items to avoid making the message too long
        $displayActions = $this->overdueActions->take(5);
        foreach ($displayActions as $idx => $action) {
            $log = $action->log;
            
            $target = '-';
            if ($log) {
                if ($log->employee) {
                    $target = "พนักงาน: " . $log->employee->fullname;
                } elseif ($log->machine) {
                    $target = "เครื่องจักร: " . $log->machine->name;
                } elseif ($log->location) {
                    $target = "พื้นที่: " . $log->location->location_name;
                }
            }

            $issue = $log->checkpoint->title ?? $log->checkpoint_title_snapshot ?? 'N/A';
            // Carbon 3 returns a signed float and defaults $absolute to false, so
            // now()->diffInHours($past) is negative. Diff from due_date forward.
            $overdueHours = (int) $action->due_date->diffInHours(now());
            
            $num = $idx + 1;
            $message .= "{$num}. {$issue}\n";
            $message .= "   📌 {$target}\n";
            $message .= "   ⚠️ สาเหตุ: {$action->root_cause}\n";
            $message .= "   ⏰ ล่าช้า: {$overdueHours} ชม.\n\n";
        }

        if ($count > 5) {
            $more = $count - 5;
            $message .= "...และอีก {$more} รายการ\n\n";
        }

        $message .= "กรุณาเข้าสู่ระบบ HygienePro เพื่อตรวจสอบและติดตามผลโดยด่วนครับ";

        return $message;
    }
}
