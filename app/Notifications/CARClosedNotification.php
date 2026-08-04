<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\CorrectiveAction;

class CARClosedNotification extends Notification
{
    use Queueable;

    public $action;

    /**
     * Create a new notification instance.
     */
    public function __construct(CorrectiveAction $action)
    {
        $this->action = $action;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $log = $this->action->log;
        $checkpointName = $log?->checkpoint_title_snapshot ?? 'ไม่ระบุจุดตรวจ';
        $place = $log?->machine ? $log->machine->name : ($log?->location ? $log->location->location_name : 'ไม่ระบุพื้นที่');
        
        $qaName = auth()->user() ? auth()->user()->name : 'QA';

        return (new MailMessage)
            ->subject('🎉 งานแก้ไขได้รับการอนุมัติและปิดงาน (Closed)')
            ->greeting('เรียนคุณ ' . $notifiable->name)
            ->line('ผลการแก้ไขปัญหาของคุณได้รับการทวนสอบและอนุมัติโดย ' . $qaName . ' เรียบร้อยแล้ว')
            ->line("จุดตรวจ/สถานที่: {$checkpointName} ({$place})")
            ->action('ดูรายละเอียด', route('corrective.index'))
            ->line('ขอบคุณที่ให้ความร่วมมือในการแก้ไขปัญหาอย่างรวดเร็วครับ');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $log = $this->action->log;
        $checkpointName = $log?->checkpoint_title_snapshot ?? 'ไม่ระบุจุดตรวจ';
        
        return [
            'type' => 'car_closed',
            'action_id' => $this->action->id,
            'title' => 'งานแก้ไขได้รับการอนุมัติ (Closed)',
            'message' => "งาน: {$checkpointName} อนุมัติผ่านแล้ว",
            'url' => route('corrective.index'),
            'icon' => 'bi-check-circle-fill text-success'
        ];
    }
}
