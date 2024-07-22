<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('admin_users')->insert([
            'name' => 'Sha Admin',
            'email' => 'admin@bwabank.com',
            'password' => bcrypt('admin')
        ]);
    }
}
