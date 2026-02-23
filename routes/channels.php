<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::routes([
    'middleware' => ['auth:sanctum', 'json.accept'],
]);

Broadcast::channel('user.{userId}', function ($user, int $userId): bool {
    return (int) $user->id === $userId;
});

Broadcast::channel('volunteer.{volunteerId}', function ($user, int $volunteerId): bool {
    return (int) $user->id === $volunteerId && $user->role === 'volunteer';
});

Broadcast::channel('volunteers.live', function ($user): bool {
    return $user->role === 'volunteer';
});
