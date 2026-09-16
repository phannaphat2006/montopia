<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InquiryAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_creates_a_linked_project_and_repeated_acceptance_preserves_it(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client', 'email' => 'owner@example.com']);
        $inquiry = $this->inquiry('OWNER@example.com');
        $response = $this->actingAs($staff)->postJson($this->replyUrl($inquiry), $this->acceptance())
            ->assertCreated()->assertJsonPath('project_created', true);
        $project = Project::findOrFail($response->json('project_id'));
        $this->assertSame($client->id, $project->client_user_id);
        $this->assertSame('planned', $project->status);
        $this->assertSame(0, $project->progress_percent);
        $this->assertSame('0.00', $project->total_budget);
        $this->assertSame(now(config('monstopia.business_timezone'))->toDateString(), $project->start_date->toDateString());
        $this->assertNull($project->end_date);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'status' => 'accepted']);
        $this->assertDatabaseHas('project_updates', ['project_id' => $project->id, 'user_id' => $staff->id, 'visible_to_client' => false]);
        $this->assertStringContainsString($inquiry->project_scope, $project->updates()->first()->body);
        $project->update(['status' => 'review', 'progress_percent' => 55, 'total_budget' => 250000]);
        $this->actingAs($staff)->postJson($this->replyUrl($inquiry), $this->acceptance())
            ->assertCreated()->assertJsonPath('project_created', false)->assertJsonPath('project_id', $project->id);
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseCount('project_updates', 1);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'status' => 'review', 'progress_percent' => 55, 'total_budget' => 250000]);
        $this->actingAs($client)->getJson('/api/client/projects')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_acceptance_without_a_client_creates_an_unassigned_project_that_can_be_linked_later(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $inquiry = $this->inquiry();
        $response = $this->actingAs($admin)->postJson($this->replyUrl($inquiry), $this->acceptance())->assertCreated();
        $project = Project::findOrFail($response->json('project_id'));
        $this->assertNull($project->client_user_id);
        $this->assertDatabaseCount('users', 1);
        $this->actingAs($admin)->getJson('/api/admin/projects')->assertOk()->assertJsonCount(1, 'data');
        $client = User::factory()->create(['role' => 'client']);
        $this->actingAs($client)->getJson('/api/client/projects')->assertOk()->assertJsonCount(0, 'data');
        $data = $project->only('project_name', 'client_name', 'inquiry_id');
        $data += ['client_user_id' => null, 'total_budget' => 90000, 'start_date' => today()->toDateString()];
        $this->actingAs($admin)->putJson('/api/admin/projects/'.$project->id, $data)->assertOk();
        $data['client_user_id'] = $client->id;
        $this->actingAs($admin)->putJson('/api/admin/projects/'.$project->id, $data)->assertOk();
        $this->actingAs($client)->getJson('/api/client/projects')->assertOk()->assertJsonCount(1, 'data');
        $other = User::factory()->create(['role' => 'client']);
        $this->actingAs($other)->getJson('/api/client/projects')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_matching_staff_email_is_never_used_as_a_client_account(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff', 'email' => 'owner@example.com']);
        $inquiry = $this->inquiry();
        $response = $this->actingAs($staff)->postJson($this->replyUrl($inquiry), $this->acceptance())->assertCreated();
        $this->assertNull(Project::findOrFail($response->json('project_id'))->client_user_id);
    }

    public function test_other_reply_statuses_do_not_create_projects(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        foreach (['pending', 'contacted', 'rejected'] as $status) {
            $inquiry = $this->inquiry();
            $this->actingAs($staff)->postJson($this->replyUrl($inquiry), ['status' => $status, 'reply_message' => 'ตอบกลับทดสอบ'])
                ->assertCreated()->assertJsonPath('project_id', null)->assertJsonPath('project_created', false);
        }
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_project_creation_failure_rolls_back_acceptance_and_reply(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $inquiry = $this->inquiry();
        $this->mock(ProjectActivityService::class, function ($mock) {
            $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulated storage failure'));
        });
        $this->actingAs($staff)->postJson($this->replyUrl($inquiry), $this->acceptance())->assertStatus(500);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'status' => 'pending']);
        $this->assertDatabaseCount('inquiry_replies', 0);
        $this->assertDatabaseCount('projects', 0);
        Mail::assertNothingSent();
    }

    public function test_sync_repairs_previously_accepted_inquiries_without_duplicates(): void
    {
        $accepted = $this->inquiry();
        $accepted->update(['status' => 'accepted']);
        $this->inquiry();
        $this->artisan('monstopia:sync-accepted-inquiries --dry-run')->assertSuccessful();
        $this->assertDatabaseCount('projects', 0);
        $this->artisan('monstopia:sync-accepted-inquiries')->assertSuccessful();
        $this->artisan('monstopia:sync-accepted-inquiries')->assertSuccessful();
        $this->assertDatabaseCount('projects', 1);
        $this->assertDatabaseHas('projects', ['inquiry_id' => $accepted->id]);
        $this->assertDatabaseCount('project_updates', 1);
    }

    public function test_guests_and_clients_cannot_accept_work(): void
    {
        $inquiry = $this->inquiry();
        $this->postJson($this->replyUrl($inquiry), $this->acceptance())->assertUnauthorized();
        $client = User::factory()->create(['role' => 'client']);
        $this->actingAs($client)->postJson($this->replyUrl($inquiry), $this->acceptance())->assertForbidden();
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'status' => 'pending']);
    }

    public function test_acceptance_uses_the_thai_business_date_after_utc_midnight_difference(): void
    {
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-09-16 20:00:00', 'UTC'));
        try {
            $staff = User::factory()->create(['role' => 'staff']);
            $inquiry = $this->inquiry();
            $response = $this->actingAs($staff)->postJson($this->replyUrl($inquiry), $this->acceptance())->assertCreated();
            $this->assertSame('2026-09-17', Project::findOrFail($response->json('project_id'))->start_date->toDateString());
        } finally {
            $this->travelBack();
        }
    }

    private function inquiry(string $email = 'owner@example.com'): Inquiry
    {
        return Inquiry::create(['client_name' => 'บริษัททดสอบ', 'client_email' => $email, 'client_phone' => '0812345678', 'budget_range' => '100,000–300,000 บาท', 'project_scope' => 'ระบบบริหารโครงการและรายงานที่ลูกค้าต้องการ']);
    }

    private function acceptance(): array
    {
        return ['status' => 'accepted', 'reply_message' => 'รับดำเนินงานแล้ว'];
    }

    private function replyUrl(Inquiry $inquiry): string
    {
        return '/api/admin/inquiries/'.$inquiry->id.'/reply';
    }
}
