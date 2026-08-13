<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\CorrectiveAction;

class CARResolvedNotification extends Notification
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
        $channels = ['database'];
        if (method_exists($notifiable, 'wantsEmailFor') && $notifiable->wantsEmailFor('email_car_resolved')) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $log = $this->action->log;
        $checkpointName = $log?->checkpoint_title_snapshot ?? 'ไม่ระบุจุดตรวจ';
        $place = $log?->machine ? $log->machine->name : ($log?->location ? $log->location->location_name : 'ไม่ระบุพื้นที่');
        
        $assigneeName = $this->action->assignee ? $this->action->assignee->name : 'เจ้าหน้าที่';

        return (new MailMessage)
            ->subject('✅ แจ้งซ่อม/สั่งแก้ไขดำเนินการเสร็จสิ้น (รอทวนสอบ)')
            ->greeting('เรียนคุณ ' . $notifiable->name)
            ->line('ฝ่ายผลิต (' . $assigneeName . ') ได้ดำเนินการแก้ไขปัญหาเสร็จสิ้นแล้ว')
            ->line("จุดตรวจ/สถานที่: {$checkpointName} ({$place})")
            ->line('วิธีการแก้ไข: ' . $this->action->action_taken)
            ->action('คลิกเพื่อทวนสอบผล (Verify)', route('inspection.verification'))
            ->line('กรุณาเข้าสู่ระบบเพื่อตรวจสอบและปิดงาน (Close) ต่อไป');
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
        $place = $log?->machine ? $log->machine->name : ($log?->location ? $log->location->location_name : 'ไม่ระบุพื้นที่');

        return [
            'type' => 'car_resolved',
            'action_id' => $this->action->id,
            'title' => 'มีการแจ้งซ่อม/สั่งแก้ไขที่ดำเนินการเสร็จแล้ว',
            'message' => "รอทวนสอบผล: {$checkpointName} ({$place})",
            'url' => route('inspection.verification'),
            'icon' => 'bi-check2-circle'
        ];
    }
}
