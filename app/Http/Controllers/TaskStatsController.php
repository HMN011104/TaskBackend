<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Carbon\Carbon;

class TaskStatsController extends Controller
{
    public function index(Request $request)
    {
        // Lấy tháng cần thống kê, mặc định là tháng hiện tại (YYYY-MM)
        $monthParam = $request->query('month', Carbon::now()->format('Y-m'));
        [$year, $month] = explode('-', $monthParam);

        // Lọc task đã hoàn thành trong tháng
        $tasks = Task::whereYear('updated_at', $year)
            ->whereMonth('updated_at', $month)
            ->where('status', 1)
            ->get();

        // Gom nhóm theo tuần trong tháng
        $grouped = [];
        foreach ($tasks as $task) {
            $week = Carbon::parse($task->updated_at)->weekOfMonth;
            $grouped[$week][] = $task;
        }

        // Tạo mảng kết quả
        $result = [];
        foreach ($grouped as $week => $items) {
            $result[] = [
                'week' => $week,
                'count' => count($items),
            ];
        }

        return response()->json($result);
    }
}
