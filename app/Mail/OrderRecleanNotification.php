<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\InspectionSession;
use Illuminate\Support\Collection;

class OrderRecleanNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $session;
    public $logs;
    public $comment;

    /**
     * Create a new message instance.
     */
    public function __construct(InspectionSession $session, Collection $logs, $comment = null)
    {
        $this->session = $session;
        // Ensure we load relationships needed for the view
        $this->logs = $logs;
        $this->comment = $comment;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $deptName = $this->session->department->dept_name ?? 'Department';
        return new Envelope(
            subject: '⚠️ Action Required: Re-clean Ordered for ' . $deptName,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.inspections.reclean_order',
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
