<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\InspectionSession;

class InspectionSessionFinished extends Mailable
{
    use Queueable, SerializesModels;

    public $session;
    public $stats;

    /**
     * Create a new message instance.
     */
    public function __construct(InspectionSession $session, array $stats)
    {
        $this->session = $session;
        $this->stats = $stats;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'แจ้งเตือน: ตรวจเสร็จสิ้นรอทวนสอบ (Inspection Finished) - ' . ucfirst($this->session->type),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.inspections.finished',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
