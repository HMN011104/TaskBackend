<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;
use Carbon\Carbon;

class TaskController extends Controller
{
    public function index(Request $request) {
        return $request->user()->tasks()->orderBy('deadline','asc')->get();
    }

    public function store(Request $request) {
        $request->validate([
            'title'=>'required'
        ]);

        $task = $request->user()->tasks()->create($request->all());
        return response()->json($task,201);
    }

    public function update(Request $request, $id) {
        $task = $request->user()->tasks()->findOrFail($id);
        $task->update($request->all());
        return response()->json($task);
    }

    public function destroy(Request $request, $id) {
        $task = $request->user()->tasks()->findOrFail($id);
        $task->delete();
        return response()->json(['message'=>'Deleted']);
    }
 
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
