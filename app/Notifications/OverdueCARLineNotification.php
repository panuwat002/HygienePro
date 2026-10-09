<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Channels\LineMessagingChannel;
use Illuminate\Support\Collection;

/**
 * The morning CAR notice, split in two.
 *
 * It used to announce every finding that was not 'closed' as "ค้างแก้ไข
 * เกินกำหนด" with an hour count measured to the present moment. A department
 * that fixed something on the 7th was still being named in the group chat on
 * the 9th at "ล่าช้า 47 ชม.", because the hours were counting QA's unfinished
 * verification and printing it as the department's unfinished repair.
 *
 * Being publicly blamed for work you have already done is how a group learns
 * to ignore a channel. Each list now names one thing and one owner, and the
 * delay printed beside a finished repair stops at the moment it was finished.
 */
class OverdueCARLineNotification extends Notification
{
    use Queueable;

    public $overdueActions;

    public $awaitingVerification;

    public function __construct($overdueActions, $awaitingVerification = null)
    {
        $this->overdueActions = $overdueActions;
        $this->awaitingVerification = $awaitingVerification ?? collect();
    }

    public function via($notifiable)
    {
        return [LineMessagingChannel::class];
    }

    public function toLine($notifiable)
    {
        $sections = [];

        if ($this->overdueActions->isNotEmpty()) {
            $count = $this->overdueActions->count();
            $section = "🚨 *ยังไม่ได้แก้ไข เกินกำหนดแล้ว* 🚨\n";
            $section .= "จำนวน *{$count}* รายการ — แผนกเจ้าของปัญหาต้องดำเนินการ\n\n";
            $section .= $this->listOf($this->overdueActions, fn ($action) => 'ล่าช้า: '
                . (int) $action->due_date->diffInHours(now()) . ' ชม.');
            $sections[] = $section;
        }

        if ($this->awaitingVerification->isNotEmpty()) {
            $count = $this->awaitingVerification->count();
            $section = "🔍 *แก้ไขแล้ว รอ QA ทวนสอบ*\n";
            $section .= "จำนวน *{$count}* รายการ — แผนกที่แก้ไขทำงานเสร็จแล้ว รอ QA กดทวนสอบเพื่อปิดใบ\n\n";
            $section .= $this->listOf($this->awaitingVerification, function ($action) {
                $waiting = (int) $action->resolved_at->diffInHours(now());
                $by = $action->resolver?->name;

                // The delay on the repair itself, frozen at the moment it was
                // finished. It stops climbing because the repair stopped.
                $late = $action->due_date && $action->resolved_at->gt($action->due_date)
                    ? ' · แก้ช้ากว่ากำหนด ' . (int) $action->due_date->diffInHours($action->resolved_at) . ' ชม.'
                    : '';

                return 'แก้ไขโดย: ' . ($by ?? '-') . ' · รอทวนสอบ ' . $waiting . ' ชม.' . $late;
            });
            $sections[] = $section;
        }

        if (empty($sections)) {
            return '';
        }

        return implode("\n", $sections)
            . "กรุณาเข้าสู่ระบบ HygienePro เพื่อตรวจสอบและติดตามผลครับ";
    }

    /**
     * @param  \Illuminate\Support\Collection  $actions
     * @param  callable  $footLine  what to print on the last line of each entry
     */
    private function listOf(Collection $actions, callable $footLine): string
    {
        $text = '';

        // Five at most: past that the message is long enough that people stop
        // reading it, which costs more than the entries left out.
        foreach ($actions->take(5)->values() as $idx => $action) {
            $log = $action->log;

            $target = '-';
            if ($log) {
                if ($log->employee) {
                    $target = 'พนักงาน: ' . $log->employee->fullname;
                } elseif ($log->machine) {
                    $target = 'เครื่องจักร: ' . $log->machine->name;
                } elseif ($log->location) {
                    $target = 'พื้นที่: ' . $log->location->location_name;
                }
            }

            $issue = $log->checkpoint->title ?? $log->checkpoint_title_snapshot ?? 'N/A';
            $num = $idx + 1;

            $text .= "{$num}. {$issue}\n";
            $text .= "   📌 {$target}\n";

            if (filled($action->root_cause)) {
                $text .= "   ⚠️ สาเหตุ: {$action->root_cause}\n";
            }

            $text .= '   ⏰ ' . $footLine($action) . "\n\n";
        }

        if ($actions->count() > 5) {
            $more = $actions->count() - 5;
            $text .= "...และอีก {$more} รายการ\n\n";
        }

        return $text;
    }
}
