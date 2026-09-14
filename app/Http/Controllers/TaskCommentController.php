<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskCommentController extends Controller
{
    private const ALLOWED_RICH_TEXT_TAGS = '<p><br><b><strong><i><em><u><ul><ol><li><a><span><h1><h2><h3><blockquote>';

    public function exportCsv(): StreamedResponse
    {
        $comments = TaskComment::with(['author', 'task', 'images'])
            ->orderBy('task_id')
            ->orderBy('created_at')
            ->get();

        $filename = 'apparel-soft-track-updates-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        return response()->streamDownload(function () use ($comments) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Task ID', 'Style Title', 'Buyer', 'Style No', 'Type', 'Written By', 'Date', 'Message', 'Photos']);

            foreach ($comments as $comment) {
                $plain = html_entity_decode(strip_tags(
                    (string) preg_replace('/<\/(p|div|li|h[1-6]|blockquote)>/i', ' ', (string) $comment->text)
                ), ENT_QUOTES | ENT_HTML5);

                fputcsv($out, [
                    $comment->task_id,
                    $comment->task?->title,
                    $comment->task?->buyer,
                    $comment->task?->style,
                    TaskComment::TYPES[$comment->type] ?? ucfirst(str_replace('_', ' ', $comment->type)),
                    $comment->author?->name,
                    $comment->created_at?->format('Y-m-d H:i'),
                    trim((string) preg_replace('/\s+/', ' ', $plain)),
                    $comment->images->count(),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

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
