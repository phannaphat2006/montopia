<?php

namespace Tests\Feature;

use App\Mail\ProjectActivityPublished;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use App\Services\ProjectActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        config([
            'mail.default' => 'array',
            'services.line.channel_access_token' => '',
            'services.line.target_id' => '',
        ]);
        Http::preventStrayRequests();
    }

    public function test_failure_is_saved_and_does_not_discard_workspace_progress_or_leak_smtp_errors(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $project = $this->project();
        $this->failMail();

        $response = $this->actingAs($staff)->postJson("/api/admin/projects/{$project->id}/updates", [
            'title' => 'Client review', 'body' => 'New design ready',
            'progress_percent' => 40, 'status' => 'review', 'visible_to_client' => true,
        ])->assertCreated()->assertJsonPath('data.email_status', 'failed')
            ->assertJsonPath('data.email_attempts', 1)->assertJsonPath('data.email_can_retry', true);

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'review', 'progress_percent' => 40]);
        $this->assertDatabaseHas('project_updates', ['id' => $response->json('data.id'), 'title' => 'Client review', 'email_status' => 'failed']);
        $this->assertStringNotContainsString('SMTP-SECRET', $response->getContent());
        $this->assertStringNotContainsString('SMTP-SECRET', ProjectUpdate::findOrFail($response->json('data.id'))->email_error);
    }

    public function test_team_can_retry_failed_mail_and_already_sent_is_not_sent_again(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $project = $this->project();
        $this->failMail();
        $activity = $this->record($project);
        $this->assertSame('failed', $activity->email_status);

        // Mail fake intercepts every message; this tests SMTP status without connecting.
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        config(['mail.default' => 'smtp']);
        $url = $this->retryUrl($project, $activity);
        $this->actingAs($staff)->postJson($url)->assertOk()
            ->assertJsonPath('data.email_status', 'sent')
            ->assertJsonPath('data.email_attempts', 2)
            ->assertJsonPath('data.email_can_retry', false);
        $this->assertNotNull($activity->fresh()->email_sent_at);
        $this->assertNull($activity->fresh()->email_error);
        $this->actingAs($staff)->postJson($url)->assertConflict();
        Mail::assertSent(ProjectActivityPublished::class, 1);
    }

    public function test_array_transport_is_marked_simulated_not_real_delivery(): void
    {
        Mail::fake();
        $activity = $this->record($this->project());

        $this->assertSame('simulated', $activity->email_status);
        $this->assertSame('array', $activity->email_transport);
        $this->assertNull($activity->email_sent_at);
        $this->assertSame(1, $activity->email_attempts);
        Mail::assertSent(ProjectActivityPublished::class, 1);
    }

    public function test_email_metadata_is_visible_to_team_but_not_client(): void
    {
        Mail::fake();
        $project = $this->project();
        $activity = $this->record($project);
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->getJson("/api/admin/projects/{$project->id}")->assertOk()
            ->assertJsonPath('data.updates.0.email_status', 'simulated')
            ->assertJsonPath('data.updates.0.email_attempts', 1);
        $payload = $this->actingAs($project->client)->getJson('/api/client/projects')->assertOk()->json('data.0.updates.0');
        $this->assertSame($activity->id, $payload['id']);
        foreach (['email_status', 'email_attempts', 'email_error', 'email_transport', 'email_sent_at', 'email_last_attempt_at', 'email_claim_token', 'email_notification_enabled', 'email_can_retry'] as $field) {
            $this->assertArrayNotHasKey($field, $payload);
        }
    }

    public function test_composite_mailers_are_unconfirmed_and_cannot_be_blindly_retried(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        foreach (['failover', 'roundrobin'] as $driver) {
            // The alias has a different name, proving we inspect its transport.
            config(['mail.default' => 'composite-test', 'mail.mailers.composite-test.transport' => $driver]);
            Mail::fake();
            $project = $this->project();
            $activity = $this->record($project);
            $this->assertSame('unknown', $activity->email_status);
            $this->assertSame('composite-test', $activity->email_transport);
            $this->assertNull($activity->email_sent_at);
            $this->assertFalse($activity->email_can_retry);
            $this->assertSame(1, $activity->email_attempts);
            $this->assertNotNull($activity->email_error);
            $this->actingAs($staff)->postJson($this->retryUrl($project, $activity))->assertUnprocessable();
            Mail::assertSent(ProjectActivityPublished::class, 1);
        }
    }

    public function test_array_and_log_mailer_aliases_are_simulated(): void
    {
        foreach (['array', 'log'] as $driver) {
            config(['mail.default' => 'local-test', 'mail.mailers.local-test.transport' => $driver]);
            Mail::fake();
            $activity = $this->record($this->project());
            $this->assertSame('simulated', $activity->email_status);
            $this->assertSame('local-test', $activity->email_transport);
            $this->assertNull($activity->email_sent_at);
            Mail::assertSent(ProjectActivityPublished::class, 1);
        }
    }

    public function test_direct_smtp_alias_is_accepted_without_claiming_inbox_delivery(): void
    {
        config(['mail.default' => 'company-mail', 'mail.mailers.company-mail.transport' => 'smtp']);
        Mail::fake();
        $activity = $this->record($this->project());
        $this->assertSame('sent', $activity->email_status);
        $this->assertSame('company-mail', $activity->email_transport);
        $this->assertNotNull($activity->email_sent_at);
        $this->assertNull($activity->email_error);
        $this->assertFalse($activity->email_can_retry);
        Mail::assertSent(ProjectActivityPublished::class, 1);
    }

    public function test_private_or_disabled_notifications_are_never_mailed_or_retried(): void
    {
        Mail::fake();
        $project = $this->project();
        $staff = User::factory()->create(['role' => 'staff']);
        $private = app(ProjectActivityService::class)->record($project, $staff, ['title' => 'Internal', 'visible_to_client' => false]);
        $disabled = app(ProjectActivityService::class)->record($project, $staff, ['title' => 'No notification'], false);

        foreach ([$private, $disabled] as $activity) {
            $this->assertSame('skipped', $activity->email_status);
            $this->assertSame(0, $activity->email_attempts);
            $this->actingAs($staff)->postJson($this->retryUrl($project, $activity))->assertUnprocessable();
        }
        Mail::assertNothingSent();
    }

    public function test_unlinked_client_is_skipped_and_can_be_sent_after_binding(): void
    {
        Mail::fake();
        $project = $this->project();
        $client = $project->client;
        $project->update(['client_user_id' => null]);
        $activity = $this->record($project);
        $this->assertSame('skipped', $activity->email_status);
        $this->assertSame(0, $activity->email_attempts);
        Mail::assertNothingSent();

        $project->update(['client_user_id' => $client->id]);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->postJson($this->retryUrl($project, $activity))->assertOk()
            ->assertJsonPath('data.email_status', 'simulated')->assertJsonPath('data.email_attempts', 1);
        Mail::assertSent(ProjectActivityPublished::class, 1);
    }

    public function test_archived_project_cannot_send_mail_on_retry(): void
    {
        Mail::fake();
        $project = $this->project();
        $activity = $project->updates()->create([
            'title' => 'Retry', 'email_status' => 'failed', 'email_notification_enabled' => true,
        ]);
        $project->update(['status' => 'archived']);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->postJson($this->retryUrl($project, $activity))->assertUnprocessable();
        Mail::assertNothingSent();
    }

    public function test_retry_checks_role_and_nested_project_ownership(): void
    {
        Mail::fake();
        $project = $this->project();
        $other = $this->project();
        $activity = $project->updates()->create(['title' => 'Pending', 'email_status' => 'pending', 'email_notification_enabled' => true]);
        $url = $this->retryUrl($project, $activity);
        $this->postJson($url)->assertUnauthorized();
        $this->actingAs($project->client)->postJson($url)->assertForbidden();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->postJson($this->retryUrl($other, $activity))->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_historical_unknown_and_in_flight_messages_cannot_be_blindly_resent(): void
    {
        Mail::fake();
        $project = $this->project();
        $historical = $project->updates()->create(['title' => 'Before delivery tracking']);
        $inFlight = $project->updates()->create([
            'title' => 'In flight', 'email_status' => 'sending', 'email_notification_enabled' => true,
            'email_attempts' => 1, 'email_claim_token' => 'b31c5cf0-7306-4e87-bd2e-adff50f237ab',
        ]);
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff)->postJson($this->retryUrl($project, $historical))->assertUnprocessable();
        $this->actingAs($staff)->postJson($this->retryUrl($project, $inFlight))->assertConflict();
        $team = $this->actingAs($staff)->getJson("/api/admin/projects/{$project->id}")->assertOk();
        $this->assertStringNotContainsString($inFlight->email_claim_token, $team->getContent());
        Mail::assertNothingSent();
    }

    public function test_only_one_attempt_can_claim_an_in_flight_message(): void
    {
        $project = $this->project();
        $activity = $project->updates()->create(['title' => 'Atomic', 'email_status' => 'pending', 'email_notification_enabled' => true]);
        $pending = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->once()->with($project->client->email)->andReturn($pending);
        $pending->shouldReceive('send')->once()->andReturnUsing(function () use ($activity) {
            $duplicate = app(ProjectActivityService::class)->deliver($activity->fresh());
            $this->assertSame('sending', $duplicate->email_status);
            $this->assertSame(1, $duplicate->email_attempts);

            return null;
        });

        $result = app(ProjectActivityService::class)->deliver($activity);
        $this->assertSame('simulated', $result->email_status);
        $this->assertSame(1, $result->email_attempts);
    }

    public function test_retry_is_bounded_to_three_total_delivery_attempts(): void
    {
        $project = $this->project();
        $staff = User::factory()->create(['role' => 'staff']);
        $this->failMail(3);
        $activity = $this->record($project);
        $url = $this->retryUrl($project, $activity);
        $this->actingAs($staff)->postJson($url)->assertOk()->assertJsonPath('data.email_attempts', 2);
        $this->actingAs($staff)->postJson($url)->assertOk()->assertJsonPath('data.email_attempts', 3)
            ->assertJsonPath('data.email_can_retry', false);
        $this->actingAs($staff)->postJson($url)->assertConflict();
        $this->assertSame(3, $activity->fresh()->email_attempts);
    }

    public function test_retry_endpoint_is_rate_limited(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $project = $this->project();
        $activity = $project->updates()->create(['title' => 'Historical']);
        $url = $this->retryUrl($project, $activity);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($staff)->postJson($url)->assertUnprocessable();
        }
        $this->actingAs($staff)->postJson($url)->assertTooManyRequests();
        Mail::assertNothingSent();
    }

    private function failMail(int $times = 1): void
    {
        $pending = Mockery::mock(PendingMail::class);
        Mail::shouldReceive('to')->times($times)->andReturn($pending);
        $pending->shouldReceive('send')->times($times)->andThrow(new RuntimeException('SMTP-SECRET credentials and host details'));
    }

    private function project(): Project
    {
        $client = User::factory()->create(['role' => 'client']);

        return Project::create([
            'project_name' => 'Delivery test', 'client_name' => $client->name,
            'client_user_id' => $client->id, 'total_budget' => 1000,
            'status' => 'in_progress', 'progress_percent' => 20, 'start_date' => '2026-09-19',
        ]);
    }

    private function record(Project $project): ProjectUpdate
    {
        return app(ProjectActivityService::class)->record($project, null, ['title' => 'Progress ready']);
    }

    private function retryUrl(Project $project, ProjectUpdate $activity): string
    {
        return "/api/admin/projects/{$project->id}/updates/{$activity->id}/retry-email";
    }
}
