<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskCommentController extends Controller
{
    private const ALLOWED_RICH_TEXT_TAGS = '<p><br><b><strong><i><em><u><ul><ol><li><a><span><h1><h2><h3><blockquote>';

    public function index(Task $task)
    {
        $comments = $task->comments()
            ->with(['author', 'images'])
            ->orderBy('created_at')
            ->limit(500)
            ->get()
            ->map(fn ($c) => $this->transform($c));

        return response()->json(['comments' => $comments]);
    }

    public function store(Request $request, Task $task)
    {
        $data = $request->validate([
            'text' => ['required', 'string'],
            'type' => ['nullable', 'in:comment,correction,buyer_email,reply'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:8192'],
        ]);

        $comment = $task->comments()->create([
            'user_id' => $request->user()->id,
            'type' => $data['type'] ?? 'comment',
            'text' => strip_tags($data['text'], self::ALLOWED_RICH_TEXT_TAGS),
        ]);

        foreach ($request->file('images', []) as $file) {
            $filename = 'task'.$task->id.'_c'.$comment->id.'_'.Str::random(20).'.'.$file->getClientOriginalExtension();
            $file->storeAs('task-images', $filename, 'public');

            $comment->images()->create([
                'task_id' => $task->id,
                'filename' => $filename,
                'original_name' => $file->getClientOriginalName(),
            ]);
        }

        return response()->json(['ok' => true, 'comment' => $this->transform($comment->fresh(['author', 'images']))]);
    }

    private function transform($c): array
    {
        return [
            'id' => $c->id,
            'type' => $c->type,
            'text' => $c->text,
            'author' => $c->author?->name,
            'created_at' => $c->created_at?->toIso8601String(),
            'images' => $c->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url(),
            ])->values(),
        ];
    }
}
