<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use App\Models\InspectionSession;

class InspectionStartedNotification extends Notification
{
    use Queueable;

    public $session;

    /**
     * Create a new notification instance.
     */
    public function __construct(InspectionSession $session)
    {
        $this->session = $session;
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
        return [
            'type' => 'inspection_started',
            'session_id' => $this->session->id,
            'title' => 'มีการเริ่มตรวจใหม่',
            'message' => 'Staff กำลังเริ่มการตรวจ ' . ($this->session->type === 'personnel' ? 'พนักงาน' : 'พื้นที่/เครื่องจักร'),
            'url' => route('inspection.dashboard', ['type' => $this->session->type]),
            'icon' => 'bi-play-circle'
        ];
    }
}
