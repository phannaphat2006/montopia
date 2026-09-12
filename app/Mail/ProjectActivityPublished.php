<?php

namespace App\Mail;

use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProjectActivityPublished extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Project $project, public ProjectUpdate $activity) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "อัปเดตโครงการ {$this->project->project_name} จาก MONSTOPIA");
    }

    public function content(): Content
    {
        return new Content(view: 'mail.project-activity-published');
    }
}
