<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SingleAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (!$email || !$password) {
            return;
        }

        if (User::query()->where('role', 'admin')->exists()) {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if (!$user) {
            $user = new User();
            $user->name = 'Admin';
            $user->email = $email;
        }

        $user->password = Hash::make($password);
        $user->role = 'admin';
        $user->role_locked = true;
        $user->role_verified_at = now();
        $user->is_active = true;

        $user->save();
    }
}
