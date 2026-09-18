<?php

namespace App\Services;

use App\Mail\ProjectActivityPublished;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ProjectActivityService
{
    public function record(Project $project, ?User $actor, array $attributes, bool $notifyClient = true): ProjectUpdate
    {
        $notifyClient = $notifyClient && ($attributes['visible_to_client'] ?? true);
        $activity = $project->updates()->create([
            'user_id' => $actor?->id,
            'type' => $attributes['type'] ?? 'note',
            'title' => $attributes['title'],
            'body' => $attributes['body'] ?? null,
            'from_status' => $attributes['from_status'] ?? null,
            'to_status' => $attributes['to_status'] ?? null,
            'progress_percent' => $attributes['progress_percent'] ?? null,
            'visible_to_client' => $attributes['visible_to_client'] ?? true,
            'email_notification_enabled' => $notifyClient,
            'email_status' => $notifyClient ? 'pending' : 'skipped',
        ]);

        if ($notifyClient) {
            $this->deliver($activity);
        }

        return $activity->refresh();
    }

    /**
     * Atomically claim one attempt without holding a DB transaction during SMTP.
     * A interrupted send stays "sending": automatic retries could duplicate mail
     * already accepted by SMTP. Staff must inspect the mailbox/log first.
     */
    public function deliver(ProjectUpdate $activity): ProjectUpdate
    {
        $activity->refresh();
        if (! $activity->email_notification_enabled || ! $activity->visible_to_client) {
            return $activity;
        }

        $project = $activity->project()->with('client')->firstOrFail();
        if ($project->status === 'archived' || $project->client?->role !== 'client' || ! $project->client?->email) {
            if (in_array($activity->email_status, ['pending', 'failed', 'skipped', 'simulated'], true)) {
                $activity->update([
                    'email_status' => 'skipped',
                    'email_error' => $project->status === 'archived'
                        ? 'โครงการเก็บถาวร ไม่ส่งอีเมลแจ้งความคืบหน้า'
                        : 'ยังไม่มีบัญชีลูกค้าที่ผูกกับโครงการ กรุณาผูกบัญชีก่อนส่ง',
                ]);
            }

            return $activity->refresh();
        }

        $claim = (string) Str::uuid();
        $transport = (string) config('mail.default');
        $claimed = ProjectUpdate::query()->whereKey($activity->id)
            ->where('email_notification_enabled', true)->where('visible_to_client', true)
            ->whereIn('email_status', ['pending', 'failed', 'skipped', 'simulated'])
            ->where('email_attempts', '<', 3)
            ->update([
                'email_status' => 'sending',
                'email_attempts' => DB::raw('email_attempts + 1'),
                'email_claim_token' => $claim,
                'email_last_attempt_at' => now(),
                'email_error' => null,
                'email_transport' => mb_substr($transport, 0, 40),
            ]);
        if (! $claimed) {
            return $activity->refresh();
        }

        $activity->refresh();
        try {
            Mail::to($project->client->email)->send(new ProjectActivityPublished($project, $activity));
        } catch (Throwable $error) {
            ProjectUpdate::query()->whereKey($activity->id)->where('email_claim_token', $claim)->update([
                'email_status' => 'failed',
                'email_claim_token' => null,
                // Never persist SMTP messages: they can contain credentials or recipients.
                'email_error' => 'ส่งอีเมลไม่สำเร็จ ข้อมูลความคืบหน้าถูกบันทึกแล้ว กรุณาตรวจการตั้งค่าอีเมลก่อนลองใหม่',
            ]);
            Log::warning('Project update email failed', [
                'project_id' => $project->id,
                'activity_id' => $activity->id,
                'exception' => $error::class,
            ]);

            return $activity->refresh();
        }

        // Keep this write outside the catch: if SMTP succeeded but DB failed,
        // do not mark the email failed and encourage an unsafe duplicate retry.
        // Inspect the configured transport, not the mailer name: aliases can
        // point to array/log, and composite mailers can silently fall back to log.
        $driver = config('mail.mailers.'.$transport.'.transport');
        $simulated = in_array($driver, ['array', 'log'], true);
        $unconfirmed = ! is_string($driver) || in_array($driver, ['failover', 'roundrobin'], true);
        ProjectUpdate::query()->whereKey($activity->id)->where('email_claim_token', $claim)->update([
            'email_status' => $simulated ? 'simulated' : ($unconfirmed ? 'unknown' : 'sent'),
            'email_sent_at' => $simulated || $unconfirmed ? null : now(),
            'email_claim_token' => null,
            'email_error' => $simulated
                ? 'อยู่ในโหมดทดสอบอีเมล ยังไม่ได้ส่งออกทาง SMTP'
                : ($unconfirmed
                    ? 'ยังไม่ยืนยันการส่งออกจริงจากบริการอีเมลหลายช่องทาง กรุณาตรวจกล่องจดหมายและ Log ก่อน ไม่ส่งซ้ำอัตโนมัติ'
                    : null),
        ]);

        return $activity->refresh();
    }
}
