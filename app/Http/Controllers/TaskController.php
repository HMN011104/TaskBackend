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
        $mode = $request->query('mode', 'week');
        $date = Carbon::parse($request->query('date', Carbon::now()));

        return $mode === 'month'
            ? $this->monthStats($date)
            : $this->weekStats($date);
    }

    private function weekStats(Carbon $date)
    {
        // Thứ 2 đầu tuần -> CN cuối tuần
        $start = $date->copy()->startOfWeek(Carbon::SUNDAY); // CN đầu tuần (tuỳ locale)
        $end   = $date->copy()->endOfWeek(Carbon::SATURDAY);

        $tasks = Task::where(function ($q) use ($start, $end) {
                    $q->whereBetween('deadline', [$start, $end])
                      ->orWhereBetween('updated_at', [$start, $end]);
                })->get();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $onTime = 0;
            $over   = 0;
            foreach ($tasks as $t) {
                if (!$t->deadline) continue;

                $deadline = Carbon::parse($t->deadline);
                $done     = $t->status;
                $updated  = Carbon::parse($t->updated_at);

                // Kiểm tra nếu deadline thuộc ngày đang xét
                if ($deadline->isSameDay($day)) {
                    if ($done) {
                        if ($updated->lte($deadline)) $onTime++;
                        else $over++;
                    } else {
                        if (Carbon::now()->gt($deadline)) $over++;
                    }
                }
            }
            $days[] = [
                'label'   => $day->isoFormat('dd D/M'), // CN 7/9
                'on_time' => $onTime,
                'overdue' => $over
            ];
        }

        return response()->json([
            'range' => $start->isoFormat('D/M') . ' - ' . $end->isoFormat('D/M'),
            'data'  => $days
        ]);
    }

    private function monthStats(Carbon $date)
    {
        $startMonth = $date->copy()->startOfMonth();
        $endMonth   = $date->copy()->endOfMonth();
        $tasks = Task::whereBetween('deadline', [$startMonth, $endMonth])->get();

        $weeks = [];
        $cursor = $startMonth->copy();
        for ($w = 0; $w < 4; $w++) {
            $weekStart = $cursor->copy();
            $weekEnd   = $cursor->copy()->addDays(6);
            if ($weekEnd->gt($endMonth)) $weekEnd = $endMonth;

            $onTime = 0;
            $over   = 0;
            foreach ($tasks as $t) {
                if (!$t->deadline) continue;

                $deadline = Carbon::parse($t->deadline);
                if ($deadline->between($weekStart, $weekEnd)) {
                    if ($t->status) {
                        if (Carbon::parse($t->updated_at)->lte($deadline)) $onTime++;
                        else $over++;
                    } else {
                        if (Carbon::now()->gt($deadline)) $over++;
                    }
                }
            }

            $weeks[] = [
                'label'   => $weekStart->isoFormat('D/M') . ' - ' . $weekEnd->isoFormat('D/M'),
                'on_time' => $onTime,
                'overdue' => $over
            ];

            $cursor->addWeek();
            if ($cursor->gt($endMonth)) break;
        }

        return response()->json([
            'range' => $startMonth->isoFormat('MMMM YYYY'),
            'data'  => $weeks
        ]);
    }
}
