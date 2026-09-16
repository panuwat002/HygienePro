<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewCARNotification extends Notification
{
    use Queueable;

    public $action;

    /**
     * Create a new notification instance.
     */
    public function __construct(\App\Models\CorrectiveAction $action)
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
        return ['database'];
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
        $place = $log?->employee ? $log->employee->fullname : ($log?->machine ? $log->machine->name : ($log?->location ? $log->location->location_name : 'ไม่ระบุพื้นที่/บุคคล'));

        return [
            'type' => 'new_car',
            'action_id' => $this->action->id,
            'title' => 'มีการแจ้งซ่อม/สั่งแก้ไขใหม่ (Auto-CAR)',
            'message' => "เรื่อง: {$checkpointName} ({$place})",
            'url' => route('corrective.index'),
            'icon' => 'bi-wrench-adjustable'
        ];
    }
}
