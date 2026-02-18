<?php

$baseUrl = 'http://localhost:8000';
$email = getenv('SMOKE_ADMIN_EMAIL') ?: 'admin@example.com';
$password = getenv('SMOKE_ADMIN_PASSWORD') ?: 'admin12345';

$cookieFile = tempnam(sys_get_temp_dir(), 'athar_cookie_');

function request(string $url, string $method = 'GET', ?array $payload = null, string $cookieFile = ''): array
{
    $ch = curl_init($url);

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $status,
        'body' => $body,
        'error' => $error,
    ];
}

$results = [];

$login = request(
    $baseUrl . '/admin/login',
    'POST',
    ['email' => $email, 'password' => $password],
    $cookieFile
);
$results[] = ['name' => 'Login', 'status' => $login['status']];

$checks = [
    'Me' => '/admin/me',
    'Dashboard' => '/admin/dashboard',
    'Governments' => '/admin/governments',
    'Categories' => '/admin/categories',
    'Locations' => '/admin/locations?search=&government_id=&category_id=',
    'HelpRequests' => '/admin/help-requests?status=all',
    'Tutorials' => '/admin/tutorials?search=',
    'Notifications' => '/admin/notifications?status=all',
    'Flags' => '/admin/flags?status=',
    'PlaceSubmissions' => '/admin/place-submissions?status=',
];

foreach ($checks as $name => $path) {
    $response = request($baseUrl . $path, 'GET', null, $cookieFile);
    $results[] = ['name' => $name, 'status' => $response['status']];
}

foreach ($results as $row) {
    $state = $row['status'] >= 200 && $row['status'] < 300 ? 'PASS' : 'FAIL';
    echo $row['name'] . '|' . $state . '|' . $row['status'] . PHP_EOL;
}

@unlink($cookieFile);
