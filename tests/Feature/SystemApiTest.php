<?php

namespace Tests\Feature;

use App\Mail\InquiryReplied;
use App\Models\Article;
use App\Models\CompanyProfile;
use App\Models\Inquiry;
use App\Models\Milestone;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_content_and_business_tables_are_created(): void
    {
        foreach (['users', 'portfolios', 'inquiries', 'inquiry_replies', 'projects', 'milestones', 'company_profiles', 'services', 'service_packages', 'articles'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_public_content_only_returns_published_records(): void
    {
        CompanyProfile::create([
            'name' => 'MONSTOPIA',
            'description' => 'Software development company',
            'email' => 'contact@example.com',
            'address' => 'Bangkok',
            'is_published' => true,
        ]);
        Service::create([
            'title' => 'Published Service',
            'slug' => 'published-service',
            'short_description' => 'Visible to visitors',
            'description' => 'Published service description',
            'display_order' => 1,
            'is_published' => true,
        ]);
        Service::create([
            'title' => 'Hidden Service',
            'slug' => 'hidden-service',
            'short_description' => 'Internal draft',
            'description' => 'This record must not be public',
            'display_order' => 2,
            'is_published' => false,
        ]);
        Article::create([
            'title' => 'Published Article',
            'slug' => 'published-article',
            'excerpt' => 'Visible article',
            'content' => 'Public article content',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
        Article::create([
            'title' => 'Draft Article',
            'slug' => 'draft-article',
            'excerpt' => 'Hidden article',
            'content' => 'Draft article content',
            'status' => 'draft',
        ]);

        $this->getJson('/api/company')->assertOk()->assertJsonPath('data.name', 'MONSTOPIA');
        $this->getJson('/api/services')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'published-service');
        $this->getJson('/api/articles')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'published-article');
    }

    public function test_staff_can_crud_company_services_packages_and_articles(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $companyId = $this->actingAs($staff)->postJson('/api/admin/company-profiles', [
            'name' => 'MONSTOPIA COMPANY LIMITED',
            'tagline' => 'Software Partner',
            'description' => 'Custom software development',
            'email' => 'contact@example.com',
            'phone' => '02-000-0000',
            'address' => 'Bangkok',
            'registration_number' => '1234567890123',
            'is_published' => true,
        ])->assertCreated()->json('data.id');

        $serviceId = $this->actingAs($staff)->postJson('/api/admin/services', [
            'title' => 'Web Application',
            'slug' => 'web-application',
            'short_description' => 'Business system',
            'description' => 'Web application for business operations',
            'icon_label' => 'WEB',
            'display_order' => 1,
            'is_published' => true,
        ])->assertCreated()->json('data.id');

        $packageId = $this->actingAs($staff)->postJson('/api/admin/service-packages', [
            'service_id' => $serviceId,
            'name' => 'Business Starter',
            'price_label' => 'Starting at 100,000 THB',
            'delivery_time' => '8 weeks',
            'description' => 'A starter package',
            'features' => "UX/UI\nDevelopment\nTesting",
            'is_featured' => true,
            'is_published' => true,
            'display_order' => 1,
        ])->assertCreated()->json('data.id');

        $articleId = $this->actingAs($staff)->postJson('/api/admin/articles', [
            'title' => 'Software Project Checklist',
            'slug' => 'software-project-checklist',
            'excerpt' => 'What to prepare before development',
            'content' => 'A complete checklist for project owners.',
            'image_url' => null,
            'status' => 'draft',
            'published_at' => null,
        ])->assertCreated()->json('data.id');

        $this->actingAs($staff)->putJson("/api/admin/company-profiles/{$companyId}", [
            'name' => 'MONSTOPIA COMPANY LIMITED',
            'tagline' => 'Technology Partner',
            'description' => 'Custom software development',
            'email' => 'contact@example.com',
            'phone' => null,
            'address' => 'Bangkok',
            'registration_number' => '1234567890123',
            'is_published' => true,
        ])->assertOk()->assertJsonPath('data.tagline', 'Technology Partner');

        $this->actingAs($staff)->putJson("/api/admin/services/{$serviceId}", [
            'title' => 'Enterprise Web Application',
            'slug' => 'web-application',
            'short_description' => 'Business system',
            'description' => 'Web application for business operations',
            'icon_label' => 'WEB',
            'display_order' => 1,
            'is_published' => true,
        ])->assertOk()->assertJsonPath('data.title', 'Enterprise Web Application');

        $this->actingAs($staff)->putJson("/api/admin/service-packages/{$packageId}", [
            'service_id' => $serviceId,
            'name' => 'Business Starter',
            'price_label' => 'Contact for pricing',
            'delivery_time' => '8 weeks',
            'description' => 'A starter package',
            'features' => 'UX/UI',
            'is_featured' => false,
            'is_published' => true,
            'display_order' => 1,
        ])->assertOk()->assertJsonPath('data.price_label', 'Contact for pricing');

        $this->actingAs($staff)->putJson("/api/admin/articles/{$articleId}", [
            'title' => 'Software Project Checklist',
            'slug' => 'software-project-checklist',
            'excerpt' => 'What to prepare before development',
            'content' => 'A complete checklist for project owners.',
            'image_url' => null,
            'status' => 'published',
            'published_at' => now()->subMinute()->toISOString(),
        ])->assertOk()->assertJsonPath('data.status', 'published');

        $this->actingAs($staff)->deleteJson("/api/admin/service-packages/{$packageId}")->assertNoContent();
        $this->actingAs($staff)->deleteJson("/api/admin/articles/{$articleId}")->assertNoContent();
        $this->actingAs($staff)->deleteJson("/api/admin/services/{$serviceId}")->assertNoContent();
        $this->actingAs($staff)->deleteJson("/api/admin/company-profiles/{$companyId}")->assertNoContent();

        $this->assertDatabaseMissing('company_profiles', ['id' => $companyId]);
        $this->assertDatabaseMissing('services', ['id' => $serviceId]);
        $this->assertDatabaseMissing('service_packages', ['id' => $packageId]);
        $this->assertDatabaseMissing('articles', ['id' => $articleId]);
    }

    public function test_public_inquiry_is_validated_persisted_and_notified(): void
    {
        config([
            'services.line.channel_access_token' => 'test-token',
            'services.line.target_id' => 'U123456',
        ]);
        Http::fake(['api.line.me/*' => Http::response([], 200)]);

        $response = $this->postJson('/api/inquiries', [
            'client_name' => 'Example Company',
            'client_email' => 'owner@example.com',
            'client_phone' => '081-234-5678',
            'budget_range' => '300,000 - 500,000 บาท',
            'project_scope' => 'ต้องการระบบบริหารโครงการและติดตามงวดงานสำหรับทีมภายใน',
        ]);

        $response->assertCreated()->assertJsonPath('reference', 'INQ-000001');
        $this->assertDatabaseHas('inquiries', [
            'client_email' => 'owner@example.com',
            'status' => 'pending',
        ]);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.line.me/v2/bot/message/push'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request['to'] === 'U123456');
    }

    public function test_public_portfolio_endpoint_returns_database_records(): void
    {
        Portfolio::create([
            'title' => 'Inventory Dashboard',
            'category' => 'Web Application',
            'description' => 'หน้าจอติดตามสินค้าและคำสั่งซื้อสำหรับทีมปฏิบัติการ',
            'image_url' => 'https://example.com/inventory-ui.webp',
            'technologies' => 'Laravel, MySQL, JavaScript',
        ]);

        $this->getJson('/api/portfolios')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Inventory Dashboard');
    }

    public function test_login_uses_a_hashed_password_and_session(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'a-secure-password',
            'role' => 'staff',
        ]);

        $this->assertNotSame('a-secure-password', $user->password);
        $this->assertTrue(Hash::check('a-secure-password', $user->password));

        $this->postJson('/api/auth/login', [
            'email' => 'staff@example.com',
            'password' => 'a-secure-password',
        ])->assertOk()->assertJsonPath('user.role', 'staff');

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'staff@example.com');
    }

    public function test_guest_and_client_cannot_access_staff_endpoints(): void
    {
        $this->getJson('/api/admin/projects')->assertUnauthorized();

        $client = User::factory()->create(['role' => 'client']);
        $this->actingAs($client)
            ->getJson('/api/admin/projects')
            ->assertForbidden();
    }

    public function test_client_can_only_see_projects_linked_to_their_account(): void
    {
        $client = User::factory()->create(['role' => 'client', 'email' => 'client@example.com']);
        $otherClient = User::factory()->create(['role' => 'client']);

        $visible = $this->projectFor($client, 'Client Portal');
        Milestone::create([
            'project_id' => $visible->id,
            'title' => 'UX/UI Design',
            'due_date' => '2026-10-01',
            'status' => 'in_progress',
        ]);
        $this->projectFor($otherClient, 'Confidential Project');
        $this->projectFor($client, 'Archived Project', 'archived');

        $response = $this->actingAs($client)->getJson('/api/client/projects');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.project_name', 'Client Portal')
            ->assertJsonPath('data.0.milestones.0.status', 'in_progress');

        $payload = $response->json('data.0');
        $this->assertArrayNotHasKey('total_budget', $payload);
        $this->assertStringNotContainsString('Confidential Project', $response->getContent());
    }

    public function test_staff_can_manage_the_project_lifecycle_and_reply_to_an_inquiry(): void
    {
        Mail::fake();
        $staff = User::factory()->create(['role' => 'staff']);
        $client = User::factory()->create(['role' => 'client']);
        $inquiry = Inquiry::create([
            'client_name' => 'Example Company',
            'client_email' => 'owner@example.com',
            'client_phone' => '081-234-5678',
            'budget_range' => '300,000 - 500,000 บาท',
            'project_scope' => 'ระบบบริหารงานสำหรับหลายแผนกและรายงานผู้บริหาร',
        ]);

        $projectResponse = $this->actingAs($staff)->postJson('/api/admin/projects', [
            'inquiry_id' => $inquiry->id,
            'client_user_id' => $client->id,
            'project_name' => 'Operations Platform',
            'client_name' => 'Example Company',
            'total_budget' => 450000,
            'start_date' => '2026-09-15',
        ])->assertCreated()->assertJsonPath('data.status', 'active');

        $projectId = $projectResponse->json('data.id');
        $this->actingAs($staff)->postJson("/api/admin/projects/{$projectId}/milestones", [
            'title' => 'Requirements & UX',
            'due_date' => '2026-10-15',
            'status' => 'pending',
        ])->assertCreated();

        $this->actingAs($staff)->postJson("/api/admin/inquiries/{$inquiry->id}/reply", [
            'reply_message' => 'ทีมงานได้รับข้อมูลและจะส่งแผนงานให้ตรวจสอบ',
            'status' => 'contacted',
        ])->assertCreated()->assertJsonPath('email_sent', true);

        $this->assertDatabaseHas('milestones', ['project_id' => $projectId]);
        $this->assertDatabaseHas('inquiry_replies', ['user_id' => $staff->id]);
        Mail::assertSent(InquiryReplied::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    }

    public function test_only_administrator_can_manage_users(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($staff)->getJson('/api/admin/users')->assertForbidden();

        $this->actingAs($admin)->postJson('/api/admin/users', [
            'name' => 'New Client',
            'email' => 'new-client@example.com',
            'password' => 'client-password-2026',
            'role' => 'client',
            'phone' => '089-999-9999',
        ])->assertCreated()->assertJsonPath('data.role', 'client');

        $this->assertDatabaseHas('users', ['email' => 'new-client@example.com', 'role' => 'client']);
        $this->actingAs($admin)->deleteJson("/api/admin/users/{$admin->id}")->assertUnprocessable();
    }

    private function projectFor(User $client, string $name, string $status = 'active'): Project
    {
        return Project::create([
            'client_user_id' => $client->id,
            'project_name' => $name,
            'client_name' => $client->name,
            'total_budget' => 250000,
            'status' => $status,
            'start_date' => '2026-09-01',
        ]);
    }
}
