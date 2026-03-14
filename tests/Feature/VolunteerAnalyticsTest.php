<?php

namespace Tests\Feature;

use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VolunteerReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VolunteerAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;
    protected User $volunteer;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('athar.platform_fee_percentage', 30);

        $this->requester = User::factory()->create(['role' => 'user']);
        $this->volunteer = User::factory()->create(['role' => 'volunteer', 'role_verified_at' => now()]);
    }

    // ─── Helpers ──────────────────────────────────────────────

    private function createCompletedRequest(array $overrides = []): HelpRequest
    {
        $feePct = (int) config('athar.platform_fee_percentage', 30);
        $serviceFee = $overrides['service_fee'] ?? 5000; // 50 EGP default
        $feeAmount = (int) round($serviceFee * $feePct / 100);
        $netAmount = $serviceFee - $feeAmount;

        return HelpRequest::create(array_merge([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'status' => 'completed',
            'payment_method' => 'cash',
            'service_fee' => $serviceFee,
            'fee_amount_cents' => $feeAmount,
            'net_amount_cents' => $netAmount,
            'urgency_level' => 'medium',
            'assistance_type' => 'navigation',
            'details' => 'Test request',
            'from_name' => 'Home',
            'from_lat' => 30.0444,
            'from_lng' => 31.2357,
            'accepted_at' => now()->subHours(2),
            'completed_at' => now()->subHour(),
            'cleared_at' => now()->subHour(),
        ], $overrides));
    }

    private function createReview(HelpRequest $hr, int $rating, ?string $comment = null): VolunteerReview
    {
        return VolunteerReview::create([
            'help_request_id' => $hr->id,
            'reviewer_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    // ─── 1. GET /api/volunteer/impact ──────────────────────────

    public function test_impact_returns_overview_structure(): void
    {
        $hr = $this->createCompletedRequest();
        $this->createReview($hr, 5, 'Excellent!');

        Sanctum::actingAs($this->volunteer);

        $response = $this->getJson('/api/volunteer/impact');
        $response->assertOk()->assertJsonPath('success', true);

        $data = $response->json('data');

        // Backward compat keys
        $this->assertArrayHasKey('counts', $data);
        $this->assertArrayHasKey('impact', $data);

        // New overview keys
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('this_month', $data);
        $this->assertArrayHasKey('weekly_activity', $data);
        $this->assertArrayHasKey('request_types', $data);

        // Summary checks
        $this->assertEquals(1, $data['summary']['completed_requests_count']);
        $this->assertEquals('EGP', $data['summary']['currency']);
        $this->assertEquals(5.0, $data['summary']['avg_rating']);
        $this->assertEquals(1, $data['summary']['reviews_count']);

        // Weekly activity is array of 7 items
        $this->assertCount(7, $data['weekly_activity']);

        // Request types
        $this->assertNotEmpty($data['request_types']);
        $this->assertEquals('navigation', $data['request_types'][0]['type']);
    }

    public function test_impact_net_earnings_calculated_correctly(): void
    {
        // 100 EGP service_fee = 10000 cents, 30% fee = 3000, net = 7000 = 70.00 EGP
        $this->createCompletedRequest(['service_fee' => 10000]);

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/impact');
        $response->assertOk();

        $this->assertEquals(70.00, $response->json('data.summary.net_earnings_all_time'));
    }

    public function test_impact_requires_volunteer_role(): void
    {
        Sanctum::actingAs($this->requester);
        $this->getJson('/api/volunteer/impact')->assertStatus(403);
    }

    // ─── 2. GET /api/volunteer/history ────────────────────────

    public function test_history_returns_expanded_structure(): void
    {
        $this->createCompletedRequest();

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/history');
        $response->assertOk()->assertJsonPath('success', true);

        $data = $response->json('data');

        // Backward compat
        $this->assertArrayHasKey('counts', $data);
        $this->assertArrayHasKey('impact', $data);

        // New keys
        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('filters', $data);

        $this->assertEquals('EGP', $data['summary']['currency']);

        // Data items
        $this->assertCount(1, $data['data']);
        $item = $data['data'][0];
        $this->assertArrayHasKey('gross_amount', $item);
        $this->assertArrayHasKey('fee_amount', $item);
        $this->assertArrayHasKey('net_amount', $item);
        $this->assertArrayHasKey('user', $item);
        $this->assertArrayHasKey('initials', $item['user']);
    }

    public function test_history_filters_by_date_range(): void
    {
        $this->createCompletedRequest(['completed_at' => now()->subDays(5)]);
        $this->createCompletedRequest([
            'completed_at' => now()->subDays(60),
            'accepted_at' => now()->subDays(60)->subHour(),
            'cleared_at' => now()->subDays(60),
        ]);

        Sanctum::actingAs($this->volunteer);
        $from = now()->subDays(10)->toDateString();
        $to = now()->toDateString();

        $response = $this->getJson("/api/volunteer/history?from={$from}&to={$to}");
        $response->assertOk();

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_history_filters_by_assistance_type(): void
    {
        $this->createCompletedRequest(['assistance_type' => 'navigation']);
        $this->createCompletedRequest(['assistance_type' => 'wheelchair_assist']);

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/history?assistance_type=navigation');
        $response->assertOk();

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_history_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createCompletedRequest(['completed_at' => now()->subMinutes($i * 10)]);
        }

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/history?per_page=2&page=1');
        $response->assertOk();

        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals(5, $response->json('data.meta.total'));
    }

    // ─── 3. GET /api/volunteer/analytics/earnings ─────────────

    public function test_earnings_returns_correct_structure(): void
    {
        $this->createCompletedRequest(['service_fee' => 10000]); // 100 EGP

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/earnings');
        $response->assertOk()->assertJsonPath('success', true);

        $data = $response->json('data');

        $this->assertArrayHasKey('summary', $data);
        $this->assertArrayHasKey('fee_info', $data);
        $this->assertArrayHasKey('monthly_net_earnings', $data);

        $summary = $data['summary'];
        $this->assertEquals('EGP', $summary['currency']);
        $this->assertEquals(100.00, $summary['gross_earnings']);
        $this->assertEquals(30.00, $summary['platform_fees']);
        $this->assertEquals(70.00, $summary['net_earnings']);
        $this->assertEquals(30, $summary['service_fee_percentage']);

        // Fee info
        $this->assertStringContainsString('30%', $data['fee_info']['description']);

        // Monthly chart has entries
        $this->assertNotEmpty($data['monthly_net_earnings']);
    }

    public function test_earnings_cleared_vs_pending(): void
    {
        // Cleared
        $this->createCompletedRequest(['service_fee' => 10000, 'cleared_at' => now()]);
        // Not cleared (card, pending)
        $this->createCompletedRequest([
            'service_fee' => 8000,
            'payment_method' => 'card',
            'cleared_at' => null,
        ]);

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/earnings');
        $response->assertOk();

        $summary = $response->json('data.summary');
        // Gross = 10000 + 8000 = 18000 cents = 180 EGP
        $this->assertEquals(180.00, $summary['gross_earnings']);
        // Net = (10000 * 0.7) + (8000 * 0.7) = 7000 + 5600 = 12600 cents = 126 EGP
        $this->assertEquals(126.00, $summary['net_earnings']);
        // Cleared = 7000 cents = 70 EGP
        $this->assertEquals(70.00, $summary['cleared_earnings']);
        // Pending = 126 - 70 = 56 EGP
        $this->assertEquals(56.00, $summary['pending_clearance']);
    }

    // ─── 4. GET /api/volunteer/analytics/performance ──────────

    public function test_performance_returns_correct_structure(): void
    {
        $hr1 = $this->createCompletedRequest();
        $hr2 = $this->createCompletedRequest();
        $this->createReview($hr1, 5, 'Great');
        $this->createReview($hr2, 4, 'Good');

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/performance');
        $response->assertOk()->assertJsonPath('success', true);

        $data = $response->json('data');

        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('badges', $data);

        $metrics = $data['metrics'];
        $this->assertEquals(5, $metrics['average_rating_out_of']);
        $this->assertEquals(2, $metrics['completed_requests']);
        $this->assertEquals(2, $metrics['positive_reviews']); // both >= 4
        $this->assertEquals(1, $metrics['five_star_ratings']);
        $this->assertEquals(1, $metrics['users_helped']); // same requester
        $this->assertIsNumeric($metrics['on_time_rate']);
    }

    public function test_performance_on_time_rate(): void
    {
        // 3 completed, 1 cancelled after assignment
        for ($i = 0; $i < 3; $i++) {
            $this->createCompletedRequest();
        }
        HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'status' => 'cancelled',
            'payment_method' => 'cash',
            'service_fee' => 0,
            'fee_amount_cents' => 0,
            'net_amount_cents' => 0,
            'urgency_level' => 'low',
            'assistance_type' => 'navigation',
            'from_name' => 'A',
            'from_lat' => 30.0,
            'from_lng' => 31.0,
            'accepted_at' => now()->subHours(3),
            'cancelled_at' => now()->subHours(2),
        ]);

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/performance');
        $response->assertOk();

        // 3 completed out of 4 assigned = 75%
        $this->assertEquals(75.0, $response->json('data.metrics.on_time_rate'));
    }

    public function test_performance_badges_top_rated(): void
    {
        $hr = $this->createCompletedRequest();
        $this->createReview($hr, 5, 'Perfect');

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/performance');
        $response->assertOk();

        $badges = collect($response->json('data.badges'));
        $this->assertTrue($badges->contains('code', 'top_rated'));
    }

    // ─── 5. GET /api/volunteer/analytics/reviews ──────────────

    public function test_reviews_returns_correct_structure(): void
    {
        $hr1 = $this->createCompletedRequest();
        $hr2 = $this->createCompletedRequest();

        $requester2 = User::factory()->create(['role' => 'user', 'name' => 'Layla Abdullah']);
        $hr2->update(['requester_id' => $requester2->id, 'user_id' => $requester2->id]);

        $this->createReview($hr1, 5, 'Wonderful service');
        VolunteerReview::create([
            'help_request_id' => $hr2->id,
            'reviewer_id' => $requester2->id,
            'volunteer_id' => $this->volunteer->id,
            'rating' => 4,
            'comment' => 'Very helpful',
        ]);

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/reviews');
        $response->assertOk()->assertJsonPath('success', true);

        $data = $response->json('data');

        // Summary
        $this->assertArrayHasKey('summary', $data);
        $this->assertEquals(2, $data['summary']['total_reviews']);
        $this->assertArrayHasKey('distribution', $data['summary']);
        $this->assertEquals(1, $data['summary']['distribution']['5']);
        $this->assertEquals(1, $data['summary']['distribution']['4']);
        $this->assertEquals(0, $data['summary']['distribution']['3']);

        // Data items
        $this->assertCount(2, $data['data']);
        $item = $data['data'][0];
        $this->assertArrayHasKey('reviewer', $item);
        $this->assertArrayHasKey('initials', $item['reviewer']);
        $this->assertArrayHasKey('rating', $item);
        $this->assertArrayHasKey('comment', $item);
        $this->assertArrayHasKey('date', $item);

        // Meta
        $this->assertEquals(2, $data['meta']['total']);
    }

    public function test_reviews_filter_by_rating(): void
    {
        $hr1 = $this->createCompletedRequest();
        $hr2 = $this->createCompletedRequest();
        $this->createReview($hr1, 5);

        $requester2 = User::factory()->create(['role' => 'user']);
        $hr2->update(['requester_id' => $requester2->id, 'user_id' => $requester2->id]);
        VolunteerReview::create([
            'help_request_id' => $hr2->id,
            'reviewer_id' => $requester2->id,
            'volunteer_id' => $this->volunteer->id,
            'rating' => 3,
        ]);

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/reviews?rating=5');
        $response->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals(5, $response->json('data.data.0.rating'));
    }

    public function test_reviews_pagination(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $req = User::factory()->create(['role' => 'user']);
            $hr = $this->createCompletedRequest(['requester_id' => $req->id, 'user_id' => $req->id]);
            VolunteerReview::create([
                'help_request_id' => $hr->id,
                'reviewer_id' => $req->id,
                'volunteer_id' => $this->volunteer->id,
                'rating' => 5,
            ]);
        }

        Sanctum::actingAs($this->volunteer);
        $response = $this->getJson('/api/volunteer/analytics/reviews?per_page=2&page=1');
        $response->assertOk();

        $this->assertCount(2, $response->json('data.data'));
        $this->assertEquals(5, $response->json('data.meta.total'));
    }

    // ─── 6. Rate volunteer endpoint ───────────────────────────

    public function test_rate_volunteer_creates_review(): void
    {
        $hr = $this->createCompletedRequest();

        Sanctum::actingAs($this->requester);
        $response = $this->postJson("/api/help-requests/{$hr->id}/rate", [
            'rating' => 5,
            'comment' => 'Outstanding service!',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $this->assertEquals(5, $response->json('data.rating'));
        $this->assertEquals('Outstanding service!', $response->json('data.comment'));

        $this->assertDatabaseHas('volunteer_reviews', [
            'help_request_id' => $hr->id,
            'reviewer_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'rating' => 5,
        ]);
    }

    public function test_rate_volunteer_requires_completed_status(): void
    {
        $hr = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'status' => 'active',
            'payment_method' => 'cash',
            'service_fee' => 0,
            'urgency_level' => 'low',
            'assistance_type' => 'navigation',
            'from_name' => 'A',
            'from_lat' => 30.0,
            'from_lng' => 31.0,
        ]);

        Sanctum::actingAs($this->requester);
        $this->postJson("/api/help-requests/{$hr->id}/rate", [
            'rating' => 5,
        ])->assertStatus(422);
    }

    public function test_rate_volunteer_upserts_on_duplicate(): void
    {
        $hr = $this->createCompletedRequest();

        Sanctum::actingAs($this->requester);
        $this->postJson("/api/help-requests/{$hr->id}/rate", ['rating' => 3]);
        $this->postJson("/api/help-requests/{$hr->id}/rate", ['rating' => 5]);

        $this->assertEquals(1, VolunteerReview::where('help_request_id', $hr->id)->count());
        $this->assertEquals(5, VolunteerReview::where('help_request_id', $hr->id)->first()->rating);
    }

    // ─── 7. Settlement fields computed on complete() ──────────

    public function test_complete_computes_settlement_fields(): void
    {
        // Create an active request
        $hr = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'status' => 'active',
            'payment_method' => 'cash',
            'service_fee' => 10000, // 100 EGP
            'urgency_level' => 'high',
            'assistance_type' => 'navigation',
            'from_name' => 'Start',
            'from_lat' => 30.0,
            'from_lng' => 31.0,
            'accepted_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->volunteer);
        $response = $this->postJson("/api/help-requests/{$hr->id}/complete");
        $response->assertOk();

        $hr->refresh();
        $this->assertEquals('completed', $hr->status);
        $this->assertEquals(3000, $hr->fee_amount_cents); // 30% of 10000
        $this->assertEquals(7000, $hr->net_amount_cents);  // 70% of 10000
        $this->assertNotNull($hr->cleared_at); // cash = immediately cleared
    }

    public function test_complete_card_payment_not_cleared_immediately(): void
    {
        $hr = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'status' => 'active',
            'payment_method' => 'card',
            'service_fee' => 5000,
            'urgency_level' => 'medium',
            'assistance_type' => 'navigation',
            'from_name' => 'Start',
            'from_lat' => 30.0,
            'from_lng' => 31.0,
            'accepted_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($this->volunteer);
        $this->postJson("/api/help-requests/{$hr->id}/complete")->assertOk();

        $hr->refresh();
        $this->assertEquals(1500, $hr->fee_amount_cents);
        $this->assertEquals(3500, $hr->net_amount_cents);
        $this->assertNull($hr->cleared_at); // card not cleared until Paymob callback
    }

    // ─── 8. Auth guards ───────────────────────────────────────

    public function test_analytics_endpoints_require_volunteer_role(): void
    {
        Sanctum::actingAs($this->requester);

        $this->getJson('/api/volunteer/analytics/earnings')->assertStatus(403);
        $this->getJson('/api/volunteer/analytics/performance')->assertStatus(403);
        $this->getJson('/api/volunteer/analytics/reviews')->assertStatus(403);
    }
}
