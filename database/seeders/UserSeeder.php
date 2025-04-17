<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'], // điều kiện để tránh tạo trùng
            [
                'name'     => 'Admin',
                'email'    => 'admin@example.com',
                'password' => Hash::make('12345678'), // bạn có thể đổi mật khẩu này
                'phone'    => '0123456789',
                'address'  => 'Hà Nội',
                'role'     => 'admin',
                'avatar'   => null,
            ]
        );
    }
}
