<?php

namespace App\Mail;

use App\Models\InspectionSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RandomAuditEscalationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $session;
    public $recheckSession;
    public $failRate;
    public $failedEmployeesCount;
    public $totalInspected;

    /**
     * Create a new message instance.
     */
    public function __construct(InspectionSession $session, InspectionSession $recheckSession, float $failRate, int $failedEmployeesCount, int $totalInspected)
    {
        $this->session = $session;
        $this->recheckSession = $recheckSession;
        $this->failRate = $failRate;
        $this->failedEmployeesCount = $failedEmployeesCount;
        $this->totalInspected = $totalInspected;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[ด่วน] แจ้งเตือนสุ่มตรวจไม่ผ่านเกณฑ์ - แผนก " . ($this->session->department->dept_name ?? 'N/A'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.inspections.random_audit_escalation',
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
