<?php

namespace Database\Seeders;

use App\Models\PaymentStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $statuses = [
            ['id' => 1, 'name' => 'Chưa thanh toán'],
            ['id' => 2, 'name' => 'Đã thanh toán'],
            ['id' => 3, 'name' => 'Hoàn tiền'],
        ];

        foreach ($statuses as $status) {
            PaymentStatus::updateOrCreate(['id' => $status['id']], [
                'name' => $status['name'],
            ]);
        }

        // Thiết lập next_status (mảng ID → json)
        PaymentStatus::where('id', 1)->update(['next_status' => json_encode([2, 4])]);
        PaymentStatus::where('id', 2)->update(['next_status' => json_encode([3])]);
        PaymentStatus::where('id', 3)->update(['next_status' => json_encode([])]);
    }
}
