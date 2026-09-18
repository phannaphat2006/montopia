<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InquiryPaginationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::fake();
    }

    public function test_admin_and_staff_can_retrieve_every_inquiry_in_a_stable_order_across_pages(): void
    {
        $records = [];
        for ($index = 0; $index < 45; $index++) {
            $inquiry = $this->inquiry($index);
            // Repeat timestamps across pages so the ID tie-breaker is exercised.
            // Cycle dates rather than inserting in date order to test both keys.
            $timestamp = '2026-09-'.str_pad((string) (10 + $index % 3), 2, '0', STR_PAD_LEFT).' 12:00:00';
            $inquiry->created_at = $timestamp;
            $inquiry->updated_at = $timestamp;
            $inquiry->save();
            $records[] = ['id' => $inquiry->id, 'created_at' => $timestamp];
        }
        usort($records, fn (array $left, array $right) => strcmp($right['created_at'], $left['created_at']) ?: $right['id'] <=> $left['id']);
        $expectedIds = array_column($records, 'id');

        foreach (['admin', 'staff'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            $allIds = [];

            foreach ([1 => 20, 2 => 20, 3 => 5] as $page => $count) {
                $response = $this->getJson('/api/admin/inquiries?page='.$page)
                    ->assertOk()
                    ->assertJsonPath('data.total', 45)
                    ->assertJsonPath('data.per_page', 20)
                    ->assertJsonPath('data.current_page', $page)
                    ->assertJsonPath('data.last_page', 3)
                    ->assertJsonPath('data.from', ($page - 1) * 20 + 1)
                    ->assertJsonPath('data.to', min($page * 20, 45))
                    ->assertJsonCount($count, 'data.data');
                $ids = array_column($response->json('data.data'), 'id');
                $this->assertSame(array_slice($expectedIds, ($page - 1) * 20, 20), $ids);
                $allIds = array_merge($allIds, $ids);
                $this->assertSame($ids, array_column($this->getJson('/api/admin/inquiries?page='.$page)->assertOk()->json('data.data'), 'id'));
            }

            $this->assertSame($expectedIds, $allIds);
            $this->assertCount(45, array_unique($allIds));
        }
    }

    public function test_invalid_page_numbers_are_rejected_instead_of_silently_returning_page_one(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['0', '-1', 'invalid', '1.5'] as $page) {
            $this->getJson('/api/admin/inquiries?page='.$page)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('page');
        }
    }

    public function test_default_empty_and_out_of_range_pages_return_consistent_metadata(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));
        $this->getJson('/api/admin/inquiries')
            ->assertOk()
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.last_page', 1)
            ->assertJsonCount(0, 'data.data');

        $this->inquiry(1);
        $this->getJson('/api/admin/inquiries?page=2')
            ->assertOk()
            ->assertJsonPath('data.current_page', 2)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.last_page', 1)
            ->assertJsonCount(0, 'data.data');
    }

    public function test_guests_and_clients_cannot_access_any_admin_inquiry_page(): void
    {
        $this->inquiry(1);
        foreach ([1, 2] as $page) {
            $this->getJson('/api/admin/inquiries?page='.$page)->assertUnauthorized();
        }

        $this->actingAs(User::factory()->create(['role' => 'client']));
        foreach ([1, 2] as $page) {
            $this->getJson('/api/admin/inquiries?page='.$page)->assertForbidden();
        }
    }

    private function inquiry(int $index): Inquiry
    {
        return Inquiry::create([
            'client_name' => 'Pagination QA '.$index,
            'client_email' => 'pagination-'.$index.'@example.test',
            'client_phone' => '0800000000',
            'budget_range' => '100,000–300,000 บาท',
            'project_scope' => 'Brief for isolated pagination testing '.$index,
            'status' => 'pending',
        ]);
    }
}
