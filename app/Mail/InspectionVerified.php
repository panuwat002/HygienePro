<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\InspectionSession;
use App\Models\User;

class InspectionVerified extends Mailable
{
    use Queueable, SerializesModels;

    public $session;
    public $supervisor;

    /**
     * Create a new message instance.
     */
    public function __construct(InspectionSession $session, User $supervisor)
    {
        $this->session = $session;
        $this->supervisor = $supervisor;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'แจ้งเตือน: ทวนสอบผลเสร็จสิ้นรออนุมัติ (Pending Manager Approval) - ' . ucfirst($this->session->type),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.inspections.verified',
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
