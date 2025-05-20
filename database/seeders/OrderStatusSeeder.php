<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\OrderStatus;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'name' => 'Chờ xác nhận'],
            ['id' => 2, 'name' => 'Đã xác nhận'],
            ['id' => 3, 'name' => 'Đang giao hàng'],
            ['id' => 4, 'name' => 'Đã giao'],
            ['id' => 5, 'name' => 'Hoàn thành'],
            ['id' => 6, 'name' => 'Đã hủy'],
            ['id' => 7, 'name' => 'Yêu cầu hoàn tiền'],
            ['id' => 8, 'name' => 'Hoàn tiền thành công'],
        ];


        foreach ($statuses as $status) {
            OrderStatus::updateOrCreate(['id' => $status['id']], [
                'name' => $status['name'],
            ]);
        }

        // Thiết lập trạng thái kế tiếp
        OrderStatus::where('id', 1)->update(['next_status' => json_encode([2, 6])]);
        OrderStatus::where('id', 2)->update(['next_status' => json_encode([3])]);
        OrderStatus::where('id', 3)->update(['next_status' => json_encode([4])]);
        OrderStatus::where('id', 4)->update(['next_status' => json_encode([5,7])]); // Đã giao → hoàn thành hoặc yêu cầu hoàn tiền
        OrderStatus::where('id', 5)->update(['next_status' => json_encode([])]);
        OrderStatus::where('id', 6)->update(['next_status' => json_encode([])]);
        OrderStatus::where('id', 7)->update(['next_status' => json_encode([8])]);    // Yêu cầu hoàn tiền → hoàn tiền thành công
        OrderStatus::where('id', 8)->update(['next_status' => json_encode([])]);
    }
}
