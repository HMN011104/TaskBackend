<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Task;

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
}
