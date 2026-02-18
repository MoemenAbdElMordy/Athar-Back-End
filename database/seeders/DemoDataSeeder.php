<?php

namespace Database\Seeders;

use App\Models\AccessibilityReport;
use App\Models\Category;
use App\Models\Flag;
use App\Models\Government;
use App\Models\HelpRequest;
use App\Models\Location;
use App\Models\Notification;
use App\Models\PlaceSubmission;
use App\Models\Tutorial;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $targetGovernments = 10;
        $targetCategories = 8;
        $targetVolunteers = 40;
        $targetUsers = 60;
        $targetLocations = 140;
        $targetHelpRequests = 120;
        $targetTutorials = 45;
        $targetFlags = 40;
        $targetNotifications = 45;
        $targetPlaceSubmissions = 60;

        $admin = User::query()->where('role', 'admin')->first();

        if (!$admin) {
            $admin = User::query()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'role_locked' => true,
                'role_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        $categories = collect([
            ['name' => 'Shopping', 'icon' => 'store'],
            ['name' => 'Healthcare', 'icon' => 'hospital'],
            ['name' => 'Tourism', 'icon' => 'landmark'],
            ['name' => 'Transport', 'icon' => 'train'],
            ['name' => 'Education', 'icon' => 'graduation-cap'],
            ['name' => 'Government', 'icon' => 'building-2'],
            ['name' => 'Parks', 'icon' => 'trees'],
            ['name' => 'Restaurants', 'icon' => 'utensils'],
        ])->map(function (array $data) {
            return Category::query()->firstOrCreate(
                ['name' => $data['name']],
                ['icon' => $data['icon']]
            );
        });

        $existingCategories = Category::query()->count();
        if ($existingCategories < $targetCategories) {
            $categories = Category::query()->orderBy('id')->get();
        }

        $governmentNames = [
            'Cairo',
            'Giza',
            'Alexandria',
            'Dakahlia',
            'Sharqia',
            'Monufia',
            'Qalyubia',
            'Faiyum',
            'Port Said',
            'Suez',
        ];

        $governments = collect($governmentNames)->map(function (string $name) {
            return Government::query()->firstOrCreate(
                ['accessible_locations' => $name],
                ['accessible_locations' => $name]
            );
        });

        if (Government::query()->count() < $targetGovernments) {
            while (Government::query()->count() < $targetGovernments) {
                Government::query()->create([
                    'accessible_locations' => fake()->city(),
                ]);
            }
            $governments = Government::query()->orderBy('id')->get();
        }

        $volunteerQuery = User::query()->where('role', 'volunteer');
        $userQuery = User::query()->where('role', 'user');

        $volunteerCount = $volunteerQuery->count();
        if ($volunteerCount < $targetVolunteers) {
            User::factory($targetVolunteers - $volunteerCount)->create([
                'role' => 'volunteer',
                'role_locked' => false,
                'role_verified_at' => now(),
                'is_active' => true,
            ]);
        }

        $userCount = $userQuery->count();
        if ($userCount < $targetUsers) {
            User::factory($targetUsers - $userCount)->create([
                'role' => 'user',
                'role_locked' => false,
                'is_active' => true,
            ]);
        }

        $volunteers = $volunteerQuery->inRandomOrder()->limit($targetVolunteers)->get();
        $users = $userQuery->inRandomOrder()->limit($targetUsers)->get();

        $locationNames = [
            'City Library',
            'Metro Station',
            'Public Park',
            'General Hospital',
            'Community Center',
            'Mall Entrance',
            'University Building',
            'Museum Gate',
            'Bus Terminal',
            'Sports Club',
            'Courthouse',
            'Tourist Info Office',
        ];

        $now = Carbon::now();

        $locations = Location::query()->with(['government', 'category'])->get();
        $missingLocations = $targetLocations - $locations->count();

        for ($i = 0; $i < max(0, $missingLocations); $i++) {
            $government = $governments->random();
            $category = $categories->random();

            $createdAt = $now->copy()->subDays(random_int(0, 10))->subHours(random_int(0, 23))->subMinutes(random_int(0, 59));

            $location = new Location();
            $location->government_id = $government->id;
            $location->name = $locationNames[array_rand($locationNames)] . ' ' . Str::upper(Str::random(3));
            $location->address = fake()->streetAddress() . ', ' . ($government->accessible_locations ?? '');
            $location->latitude = fake()->latitude(29.8, 31.4);
            $location->longitude = fake()->longitude(30.7, 32.0);
            $location->category_id = $category->id;
            $location->average_rating = fake()->randomFloat(2, 3, 5);
            $location->reviews_count = fake()->numberBetween(0, 120);
            $location->created_at = $createdAt;
            $location->updated_at = $createdAt->copy()->addHours(random_int(0, 48));
            $location->save();

            $locations->push($location);

            if (random_int(1, 100) <= 65) {
                AccessibilityReport::query()->create([
                    'location_id' => $location->id,
                    'verified' => random_int(1, 100) <= 40,
                    'wide_entrance' => random_int(1, 100) <= 55,
                    'wheelchair_accessible' => random_int(1, 100) <= 45,
                    'elevator_available' => random_int(1, 100) <= 35,
                    'ramp_available' => random_int(1, 100) <= 50,
                    'parking' => random_int(1, 100) <= 30,
                    'created_at' => $location->created_at,
                    'updated_at' => $location->updated_at,
                ]);
            }
        }

        $assistanceTypes = ['Wheelchair push', 'Navigation help', 'Transportation', 'Medical assistance', 'Stairs support'];
        $urgencyLevels = ['low', 'medium', 'high'];

        $missingHelpRequests = $targetHelpRequests - HelpRequest::query()->count();
        for ($i = 0; $i < max(0, $missingHelpRequests); $i++) {
            $requester = $users->random();
            $volunteer = random_int(1, 100) <= 70 ? $volunteers->random() : null;

            $createdAt = $now->copy()->subDays(random_int(0, 6))->subHours(random_int(0, 23))->subMinutes(random_int(0, 59));

            $statusPool = ['pending', 'in_progress', 'resolved'];
            $status = $statusPool[array_rand($statusPool)];

            $helpRequest = new HelpRequest();
            $helpRequest->requester_id = $requester->id;
            $helpRequest->volunteer_id = $volunteer?->id;
            $helpRequest->user_id = $requester->id;
            $helpRequest->assigned_admin_id = $admin->id;
            $helpRequest->status = $status;
            $helpRequest->urgency_level = $urgencyLevels[array_rand($urgencyLevels)];
            $helpRequest->assistance_type = $assistanceTypes[array_rand($assistanceTypes)];
            $helpRequest->details = fake()->sentence(12);

            $from = $locations->random();
            $to = random_int(1, 100) <= 60 ? $locations->random() : null;

            $helpRequest->from_name = $from->name;
            $helpRequest->from_lat = $from->latitude ?? fake()->latitude(29.8, 31.4);
            $helpRequest->from_lng = $from->longitude ?? fake()->longitude(30.7, 32.0);

            $helpRequest->to_name = $to?->name;
            $helpRequest->to_lat = $to?->latitude;
            $helpRequest->to_lng = $to?->longitude;

            $helpRequest->name = $requester->full_name ?: $requester->name;
            $helpRequest->phone = $requester->phone ?: fake()->phoneNumber();
            $helpRequest->location_text = $from->address;
            $helpRequest->lat = $from->latitude;
            $helpRequest->lng = $from->longitude;
            $helpRequest->message = fake()->sentence(10);

            if ($status !== 'pending') {
                $helpRequest->accepted_at = $createdAt->copy()->addMinutes(random_int(2, 40));
            }

            if ($status === 'in_progress' || $status === 'resolved') {
                $helpRequest->started_at = $helpRequest->accepted_at?->copy()->addMinutes(random_int(5, 45));
            }

            if ($status === 'resolved') {
                $helpRequest->resolved_at = $helpRequest->started_at?->copy()->addMinutes(random_int(15, 180));
                $helpRequest->completed_at = $helpRequest->resolved_at;
            }

            $helpRequest->created_at = $createdAt;
            $helpRequest->updated_at = $createdAt->copy()->addMinutes(random_int(0, 600));
            $helpRequest->save();
        }

        $tutorialCategories = ['Mobility', 'Wheelchair', 'Navigation', 'Public Transport', 'Safety'];
        $missingTutorials = $targetTutorials - Tutorial::query()->count();
        for ($i = 0; $i < max(0, $missingTutorials); $i++) {
            $createdAt = $now->copy()->subDays(random_int(0, 20))->subHours(random_int(0, 23));
            Tutorial::query()->create([
                'title' => 'Tutorial ' . Str::title(fake()->words(3, true)),
                'description' => fake()->paragraph(),
                'video_url' => 'https://www.youtube.com/watch?v=' . Str::random(11),
                'thumbnail_url' => 'https://picsum.photos/seed/' . Str::random(8) . '/400/240',
                'category' => $tutorialCategories[array_rand($tutorialCategories)],
                'is_published' => random_int(1, 100) <= 90,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addDays(random_int(0, 3)),
            ]);
        }

        $flagReasons = ['incorrect', 'closed', 'no_ramp', 'wrong_location', 'other'];
        $missingFlags = $targetFlags - Flag::query()->count();
        for ($i = 0; $i < max(0, $missingFlags); $i++) {
            $flagger = random_int(1, 100) <= 60 ? $users->random() : $volunteers->random();
            $location = $locations->random();
            $createdAt = $now->copy()->subDays(random_int(0, 10))->subHours(random_int(0, 23));

            $status = random_int(1, 100) <= 55 ? 'open' : 'resolved';

            Flag::query()->create([
                'flagger_id' => $flagger->id,
                'flaggable_type' => Location::class,
                'flaggable_id' => $location->id,
                'reason' => $flagReasons[array_rand($flagReasons)],
                'details' => fake()->sentence(16),
                'admin_note' => random_int(1, 100) <= 40 ? fake()->sentence(12) : null,
                'status' => $status,
                'handled_by_admin_id' => $status === 'resolved' ? $admin->id : null,
                'handled_at' => $status === 'resolved' ? $createdAt->copy()->addHours(random_int(1, 48)) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt->copy()->addHours(random_int(0, 72)),
            ]);
        }

        $notificationTypes = ['report', 'help_request', 'submission', 'system'];
        $notificationSeverities = ['low', 'medium', 'high'];

        $missingNotifications = $targetNotifications - Notification::query()->count();
        for ($i = 0; $i < max(0, $missingNotifications); $i++) {
            $createdAt = $now->copy()->subDays(random_int(0, 8))->subHours(random_int(0, 23))->subMinutes(random_int(0, 59));
            $type = $notificationTypes[array_rand($notificationTypes)];

            Notification::query()->create([
                'user_id' => $admin->id,
                'type' => $type,
                'title' => Str::title($type) . ' update',
                'body' => fake()->sentence(18),
                'severity' => $notificationSeverities[array_rand($notificationSeverities)],
                'notifiable_type' => null,
                'notifiable_id' => null,
                'is_read' => random_int(1, 100) <= 55,
                'read_at' => null,
                'metadata' => ['seed' => true],
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }

        $missingPlaceSubmissions = $targetPlaceSubmissions - PlaceSubmission::query()->count();
        for ($i = 0; $i < max(0, $missingPlaceSubmissions); $i++) {
            $submitter = random_int(1, 100) <= 55 ? $users->random() : $volunteers->random();
            $category = random_int(1, 100) <= 80 ? $categories->random() : null;
            $createdAt = $now->copy()->subDays(random_int(0, 9))->subHours(random_int(0, 23));

            $statusPool = ['pending', 'approved', 'rejected'];
            $status = $statusPool[array_rand($statusPool)];

            $reviewedAt = $status === 'pending' ? null : $createdAt->copy()->addHours(random_int(1, 60));

            PlaceSubmission::query()->create([
                'submitted_by' => $submitter->id,
                'name' => 'Submission ' . Str::title(fake()->words(2, true)),
                'address' => fake()->streetAddress(),
                'lat' => fake()->latitude(29.8, 31.4),
                'lng' => fake()->longitude(30.7, 32.0),
                'category_id' => $category?->id,
                'notes' => fake()->sentence(14),
                'status' => $status,
                'reviewed_by_admin_id' => $status === 'pending' ? null : $admin->id,
                'reviewed_at' => $reviewedAt,
                'rejection_reason' => $status === 'rejected' ? 'Missing accessibility details.' : null,
                'created_at' => $createdAt,
                'updated_at' => $reviewedAt ?: $createdAt,
            ]);
        }
    }
}
