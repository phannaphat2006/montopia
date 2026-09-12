<?php

namespace App\Services;

use App\Mail\ProjectActivityPublished;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProjectActivityService
{
    public function record(Project $project, ?User $actor, array $attributes, bool $notifyClient = true): ProjectUpdate
    {
        $activity = $project->updates()->create([
            'user_id' => $actor?->id,
            'type' => $attributes['type'] ?? 'note',
            'title' => $attributes['title'],
            'body' => $attributes['body'] ?? null,
            'from_status' => $attributes['from_status'] ?? null,
            'to_status' => $attributes['to_status'] ?? null,
            'progress_percent' => $attributes['progress_percent'] ?? null,
            'visible_to_client' => $attributes['visible_to_client'] ?? true,
        ]);

        if ($notifyClient && $activity->visible_to_client && $project->client?->email) {
            try {
                Mail::to($project->client->email)->send(new ProjectActivityPublished($project, $activity));
            } catch (Throwable $error) {
                Log::warning('Project update email failed', [
                    'project_id' => $project->id,
                    'activity_id' => $activity->id,
                    'exception' => $error::class,
                ]);
            }
        }

        return $activity;
    }
}
