<?php

namespace App\Mail;

use App\Models\Inquiry;
use App\Models\InquiryReply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InquiryReplied extends Mailable
{
    use Queueable,SerializesModels;

    public function __construct(public Inquiry $inquiry, public InquiryReply $reply) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'อัปเดตบรีฟโครงการจาก MONSTOPIA');
    }

    public function content(): Content
    {
        return new Content(view: 'mail.inquiry-replied');
    }
}
