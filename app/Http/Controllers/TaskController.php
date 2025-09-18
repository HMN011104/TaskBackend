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

        // Ưu tiên user đang authenticate, nếu không có thì lấy user_id từ query param
        $userId = auth()->id() ?? $request->query('user_id');
        if ($userId) $userId = (int) $userId;

        return $mode === 'month'
            ? $this->monthStats($date, $userId)
            : $this->weekStats($date, $userId);
    }

    private function weekStats(Carbon $date, $userId = null)
    {
        // Tuần: CN -> T7 (7 ngày)
        $start = $date->copy()->startOfWeek(Carbon::SUNDAY)->startOfDay();
        $end   = $date->copy()->endOfWeek(Carbon::SATURDAY)->endOfDay();

        // Lấy task của user trong khoảng (theo deadline hoặc updated_at)
        $query = Task::query();
        if ($userId) $query->where('user_id', $userId);

        $tasks = $query->where(function ($q) use ($start, $end) {
            $q->whereBetween('deadline', [$start, $end])
              ->orWhereBetween('updated_at', [$start, $end]);
        })->get();

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $day = $start->copy()->addDays($i);
            $onTime = 0;
            $over   = 0;

            foreach ($tasks as $t) {
                if (!$t->deadline) continue; // bỏ task không có deadline

                $deadline = Carbon::parse($t->deadline);
                $updated  = Carbon::parse($t->updated_at);

                if ($deadline->isSameDay($day)) {
                    if ($t->status) {
                        // đã hoàn thành => so updated_at với deadline
                        if ($updated->lte($deadline)) $onTime++;
                        else $over++;
                    } else {
                        // chưa hoàn thành và quá hạn
                        if (Carbon::now()->gt($deadline)) $over++;
                    }
                }
            }

            $days[] = [
                'label'   => $day->isoFormat('dd D/M'),
                'on_time' => $onTime,
                'overdue' => $over
            ];
        }

        return response()->json([
            'range' => $start->isoFormat('D/M') . ' - ' . $end->isoFormat('D/M'),
            'data'  => $days
        ]);
    }

    private function monthStats(Carbon $date, $userId = null)
    {
        $startMonth = $date->copy()->startOfMonth()->startOfDay();
        $endMonth   = $date->copy()->endOfMonth()->endOfDay();

        // Lấy tất cả task của user có deadline/updated_at trong tháng
        $query = Task::query();
        if ($userId) $query->where('user_id', $userId);

        $tasks = $query->where(function ($q) use ($startMonth, $endMonth) {
            $q->whereBetween('deadline', [$startMonth, $endMonth])
              ->orWhereBetween('updated_at', [$startMonth, $endMonth]);
        })->get();

        // Tạo các khoảng tuần: tuần đầu 1 -> CN đầu tháng, các tuần kế tiếp theo khối 7 ngày.
        $first = $startMonth->copy();
        $last  = $endMonth->copy();

        // tính CN đầu tháng
        $firstDayDow = $first->dayOfWeek; // 0=CN,1=T2...
        $offsetToSunday = (7 - $firstDayDow) % 7;
        $firstEnd = $first->copy()->addDays($offsetToSunday)->endOfDay();

        $ranges = [];
        // tuần đầu: 1 -> CN đầu tháng
        $ranges[] = [
            'start' => $first->copy()->startOfDay(),
            'end'   => $firstEnd->copy()
        ];

        // các tuần tiếp theo (T2 -> CN) cho tới hết tháng
        $nextStart = $firstEnd->copy()->addDay()->startOfDay();
        while ($nextStart->lte($last)) {
            $end = $nextStart->copy()->addDays(6)->endOfDay();
            if ($end->gt($last)) $end = $last->copy()->endOfDay();

            $ranges[] = [
                'start' => $nextStart->copy()->startOfDay(),
                'end'   => $end->copy()
            ];

            $nextStart = $end->copy()->addDay()->startOfDay();
        }

        // Nếu số ranges > 4, gộp các ranges từ index 3 trở đi thành cột thứ 4
        if (count($ranges) > 4) {
            $new = array_slice($ranges, 0, 3);
            $lastRange = end($ranges);
            $new[] = [
                'start' => $ranges[3]['start'],
                'end'   => $lastRange['end']
            ];
            $ranges = $new;
        }

        // Tính on_time / overdue cho từng range
        $weeks = [];
        $now = Carbon::now();
        foreach ($ranges as $r) {
            $onTime = 0;
            $over   = 0;
            foreach ($tasks as $t) {
                if (!$t->deadline) continue;

                $deadline = Carbon::parse($t->deadline);
                // kiểm tra deadline nằm trong range
                if ($deadline->between($r['start'], $r['end'])) {
                    if ($t->status) {
                        $updated = Carbon::parse($t->updated_at);
                        if ($updated->lte($deadline)) $onTime++;
                        else $over++;
                    } else {
                        if ($now->gt($deadline)) $over++;
                    }
                }
            }

            $label = $r['start']->format('j') === $r['end']->format('j')
                ? $r['start']->format('j/n')
                : $r['start']->format('j') . '-' . $r['end']->format('j/n');

            $weeks[] = [
                'label'   => $label,
                'on_time' => $onTime,
                'overdue' => $over
            ];
        }

        return response()->json([
            'range' => $startMonth->isoFormat('MMMM YYYY'),
            'data'  => $weeks
        ]);
    }
}
