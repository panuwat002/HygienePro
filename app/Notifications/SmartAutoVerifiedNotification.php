<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SmartAutoVerifiedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $autoVerifiedCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(int $autoVerifiedCount)
    {
        $this->autoVerifiedCount = $autoVerifiedCount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Only in-app for now
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'smart_auto_verified',
            'title' => '🎉 ยินดีด้วย! งานของคุณผ่านอัตโนมัติ',
            'message' => "งานตรวจความสะอาดจำนวน {$this->autoVerifiedCount} รายการของคุณ ได้รับสิทธิ์ Smart Auto-Verified เนื่องจากคุณรักษามาตรฐานได้ดีเยี่ยม!",
            'url' => '#',
        ];
    }
}
