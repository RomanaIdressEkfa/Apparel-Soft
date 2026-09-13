<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskImageController extends Controller
{
    public function store(Request $request, Task $task)
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ]);

        $file = $request->file('image');
        $filename = 'task'.$task->id.'_'.Str::random(20).'.'.$file->getClientOriginalExtension();

        $file->storeAs('task-images', $filename, 'public');

        $image = $task->images()->create([
            'filename' => $filename,
            'original_name' => $file->getClientOriginalName(),
        ]);

        return response()->json([
            'ok' => true,
            'id' => $image->id,
            'url' => $image->url(),
        ]);
    }

    public function destroy(TaskImage $image)
    {
        Storage::disk('public')->delete('task-images/'.$image->filename);
        $image->delete();

        return response()->json(['ok' => true]);
    }
}
