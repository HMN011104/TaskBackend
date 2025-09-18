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
    
    public function stats(Request $request)
    {
        $mode = $request->query('mode', 'month');
        $date = Carbon::parse($request->query('date', Carbon::now()));

        if ($mode === 'week') {
            // Lấy thứ 2 đầu tuần và CN cuối tuần
            $start = $date->copy()->startOfWeek(Carbon::MONDAY);
            $end   = $date->copy()->endOfWeek(Carbon::SUNDAY);

            $tasks = Task::whereBetween('updated_at', [$start, $end])
                         ->where('status', 1)
                         ->get();

            // Gom theo từng ngày
            $days = [];
            for ($d = 0; $d < 7; $d++) {
                $current = $start->copy()->addDays($d);
                $days[] = [
                    'label' => $current->isoFormat('dd D/M'), // CN 7/9
                    'count' => $tasks->filter(function ($t) use ($current) {
                        return Carbon::parse($t->updated_at)->isSameDay($current);
                    })->count()
                ];
            }
            return response()->json([
                'range' => $start->isoFormat('D/M') . ' - ' . $end->isoFormat('D/M'),
                'data'  => $days
            ]);
        }

        // mode = month
        $startMonth = $date->copy()->startOfMonth();
        $endMonth   = $date->copy()->endOfMonth();

        $tasks = Task::whereBetween('updated_at', [$startMonth, $endMonth])
                     ->where('status', 1)
                     ->get();

        // Gom theo từng tuần của tháng
        $weeks = [];
        $cursor = $startMonth->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lessThanOrEqualTo($endMonth)) {
            $weekStart = $cursor->copy();
            $weekEnd   = $cursor->copy()->endOfWeek(Carbon::SUNDAY);
            $label = $weekStart->isoFormat('D/M') . ' - ' .
                     min($weekEnd->isoFormat('D/M'), $endMonth->isoFormat('D/M'));

            $weeks[] = [
                'label' => $label,
                'count' => $tasks->filter(function ($t) use ($weekStart, $weekEnd) {
                    $d = Carbon::parse($t->updated_at);
                    return $d->between($weekStart, $weekEnd);
                })->count()
            ];
            $cursor->addWeek();
        }

        return response()->json([
            'range' => $startMonth->isoFormat('MMMM YYYY'),
            'data'  => $weeks
        ]);
    }
}
