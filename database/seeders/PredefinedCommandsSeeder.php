<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PredefinedCommandsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('predefined_commands')->insert([
            [
                'command' => 'give me popular hotel list',
                'command_bn' => 'জনপ্রিয় হোটেল লিস্ট দেখাও',
                'description' => 'Show list of hotels marked as popular',
                'action_query' => 'SELECT id, name, rating, location FROM hotels WHERE is_popular = 1',
                'action_type' => 'query',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'command' => 'find 001 referred users',
                'command_bn' => '০০১ রেফার্ড ইউজার দেখাও',
                'description' => 'Show all users who used referral code 001',
                'action_query' => "SELECT id, name, email, refer_code FROM users WHERE refer_code = '001'",
                'action_type' => 'query',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'command' => 'show today bookings',
                'command_bn' => 'আজকের বুকিং দেখাও',
                'description' => 'Show all hotel bookings made today',
                'action_query' => "SELECT * FROM bookings WHERE DATE(created_at) = CURDATE()",
                'action_type' => 'query',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'command' => 'show top customers',
                'command_bn' => 'সেরা কাস্টমার দেখাও',
                'description' => 'Show top customers by number of bookings',
                'action_query' => "SELECT users.id, users.name, COUNT(bookings.id) AS total_bookings FROM users JOIN bookings ON users.id = bookings.user_id GROUP BY users.id ORDER BY total_bookings DESC LIMIT 10",
                'action_type' => 'query',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'command' => 'total earnings this month',
                'command_bn' => 'এই মাসের মোট আয় দেখাও',
                'description' => 'Show total revenue generated this month',
                'action_query' => "SELECT SUM(amount) AS total_earnings FROM payments WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())",
                'action_type' => 'query',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
