<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Government;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerSqliteTrigFunctions();
    }

    public function test_mobile_api_requires_accept_json_header(): void
    {
        $response = $this->get('/api/categories');

        $response
            ->assertStatus(406)
            ->assertJsonPath('success', false);
    }

    public function test_mobile_public_endpoints_work(): void
    {
        ['location' => $location] = $this->seedLocationData();

        Review::query()->create([
            'user_id' => User::factory()->create()->id,
            'location_id' => $location->id,
            'rating' => 4,
            'comment' => 'Good access',
        ]);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/governments')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/locations?search=&category_id=&government_id=&page=1&per_page=15')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/locations/nearby?lat=30.0444&lng=31.2357&radius_km=5')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/locations/{$location->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson("/api/locations/{$location->id}/ratings?page=1&per_page=10")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_mobile_auth_and_profile_endpoints_work(): void
    {
        $registerResponse = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'full_name' => 'Test User Full',
            'email' => 'mobile-user@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $registerResponse
            ->assertCreated()
            ->assertJsonPath('success', true);

        $token = (string) $registerResponse->json('data.token');

        $this->postJson('/api/auth/login', [
            'email' => 'mobile-user@example.com',
            'password' => 'password123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $headers = $this->tokenHeaders($token);

        $this->withHeaders($headers)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->putJson('/api/profile', [
            'name' => 'Updated User',
            'full_name' => 'Updated User Full',
            'phone' => '01000000000',
            'disability_type' => 'visual',
            'mobility_aids' => 'cane',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->getJson('/api/profile/stats')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_mobile_user_contribution_endpoints_work(): void
    {
        ['location' => $location] = $this->seedLocationData();
        $user = User::factory()->create();
        $headers = $this->authHeaders($user);

        $this->withHeaders($headers)->postJson("/api/locations/{$location->id}/ratings", [
            'rating' => 5,
            'comment' => 'Excellent',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->postJson('/api/place-submissions', [
            'name' => 'New Place',
            'address' => 'Test Street',
            'latitude' => 30.10,
            'longitude' => 31.20,
            'category_id' => $location->category_id,
            'notes' => 'Submitted from test',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->getJson('/api/place-submissions/mine?status=pending&page=1&per_page=15')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->postJson("/api/locations/{$location->id}/flags", [
            'type' => 'wrong_info',
            'details' => 'Needs update',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->postJson('/api/flags', [
            'flaggable_type' => 'location',
            'flaggable_id' => $location->id,
            'reason' => 'other',
            'details' => 'Generic flag endpoint',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->getJson('/api/flags/mine?status=pending&page=1&per_page=15')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->withHeaders($headers)->putJson("/api/locations/{$location->id}/accessibility-report", [
            'wide_entrance' => true,
            'wheelchair_accessible' => true,
            'elevator_available' => false,
            'ramp_available' => true,
            'parking' => true,
            'accessible_toilet' => false,
            'notes' => 'Testing contribution',
        ])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_mobile_help_requests_and_volunteer_flow_endpoints_work(): void
    {
        $requester = User::factory()->create();
        $volunteer = User::factory()->create();
        $volunteer->forceFill(['role' => 'volunteer'])->save();
        $volunteer->refresh();

        Sanctum::actingAs($requester);
        $createResponse = $this->postJson('/api/help-requests', [
            'from_label' => 'Home',
            'from_lat' => 30.0444,
            'from_lng' => 31.2357,
            'to_label' => 'Hospital',
            'to_lat' => 30.0500,
            'to_lng' => 31.2400,
            'assistance_type' => 'navigation',
            'urgency' => 'high',
            'details' => 'Need guidance',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('success', true);

        $helpRequestId = (int) $createResponse->json('data.id');

        $this->getJson('/api/help-requests/mine?status=pending&page=1&per_page=15')
            ->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($volunteer);
        $this->postJson('/api/volunteer/status', [
            'is_live' => true,
            'lat' => 30.0444,
            'lng' => 31.2357,
        ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/volunteer/incoming?lat=30.0444&lng=31.2357&page=1&per_page=15')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/help-requests/{$helpRequestId}/accept")
            ->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($requester);
        $this->getJson("/api/help-requests/{$helpRequestId}/messages?page=1&per_page=20")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/help-requests/{$helpRequestId}/messages", [
            'message' => 'I am waiting near the gate',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($volunteer);
        $this->postJson("/api/help-requests/{$helpRequestId}/complete")
            ->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($requester);
        $declineHelpRequestId = (int) $this->postJson('/api/help-requests', [
            'from_label' => 'Office',
            'from_lat' => 30.0600,
            'from_lng' => 31.2500,
            'to_label' => 'Metro',
            'to_lat' => 30.0610,
            'to_lng' => 31.2510,
            'assistance_type' => 'other',
            'urgency' => 'low',
            'details' => 'Simple guidance',
        ])->json('data.id');

        Sanctum::actingAs($volunteer);
        $this->postJson("/api/help-requests/{$declineHelpRequestId}/decline")
            ->assertOk()
            ->assertJsonPath('success', true);

        Sanctum::actingAs($requester);
        $cancelHelpRequestId = (int) $this->postJson('/api/help-requests', [
            'from_label' => 'Mall',
            'from_lat' => 30.0700,
            'from_lng' => 31.2600,
            'to_label' => 'Home',
            'to_lat' => 30.0710,
            'to_lng' => 31.2610,
            'assistance_type' => 'finding_location',
            'urgency' => 'medium',
            'details' => 'Need quick support',
        ])->json('data.id');

        $this->postJson("/api/help-requests/{$cancelHelpRequestId}/cancel")
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    private function seedLocationData(): array
    {
        $government = Government::query()->create([
            'accessible_locations' => json_encode(['Cairo']),
        ]);

        $category = Category::query()->create([
            'name' => 'Hospital',
            'icon' => 'hospital',
        ]);

        $location = Location::query()->create([
            'government_id' => $government->id,
            'name' => 'Test Location',
            'address' => 'Test Address',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'category_id' => $category->id,
            'average_rating' => 0,
            'reviews_count' => 0,
        ]);

        return compact('government', 'category', 'location');
    }

    private function authHeaders(User $user): array
    {
        return $this->tokenHeaders($user->createToken('test-suite')->plainTextToken);
    }

    private function tokenHeaders(string $token): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ];
    }

    private function registerSqliteTrigFunctions(): void
    {
        $connection = DB::connection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo = $connection->getPdo();

        if (!method_exists($pdo, 'sqliteCreateFunction')) {
            return;
        }

        $pdo->sqliteCreateFunction('acos', static fn ($value): float => acos((float) $value), 1);
        $pdo->sqliteCreateFunction('cos', static fn ($value): float => cos((float) $value), 1);
        $pdo->sqliteCreateFunction('sin', static fn ($value): float => sin((float) $value), 1);
        $pdo->sqliteCreateFunction('radians', static fn ($value): float => deg2rad((float) $value), 1);
    }
}
