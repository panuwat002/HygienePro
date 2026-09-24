<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What is sitting on a QA manager's desk.
 *
 * Carries plain arrays rather than models: the rows are already summarised by
 * the time the command builds them, and a queued notification that holds
 * Eloquent objects reloads them on the worker.
 */
class AwaitingApprovalDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $total,
        public int $personCount,
        public int $areaCount,
        public array $rows,
    ) {
    }

    /**
     * Opting out of the email is not opting out of the work, so the bell rings
     * either way.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (method_exists($notifiable, 'wantsEmailFor')
            && $notifiable->wantsEmailFor('email_awaiting_approval')
            && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('รออนุมัติ: มีรอบตรวจ ' . $this->total . ' รอบรอคุณเซ็น')
            ->view('emails.inspections.awaiting_approval', [
                'total' => $this->total,
                'personCount' => $this->personCount,
                'areaCount' => $this->areaCount,
                'rows' => $this->rows,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'awaiting_approval_digest',
            'count' => $this->total,
            'title' => '🖊 มีงานรออนุมัติ ' . $this->total . ' รอบ',
            'message' => 'QA ทวนสอบเสร็จแล้ว รอคุณอนุมัติ — พนักงาน ' . $this->personCount
                . ' รอบ, พื้นที่/เครื่องจักร ' . $this->areaCount . ' รอบ',
            'url' => route('inspection.verification', ['tab' => 'awaiting_approval']),
        ];
    }
}
