<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskController extends Controller
{
    private const ALLOWED_RICH_TEXT_TAGS = '<p><br><b><strong><i><em><u><ul><ol><li><a><span><h1><h2><h3><blockquote>';

    public function dashboard()
    {
        return view('dashboard');
    }

    public function index()
    {
        $tasks = Task::with(['images', 'creator'])
            ->withCount('comments')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (Task $task) => $this->transform($task));

        return response()->json(['tasks' => $tasks]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        $task = Task::create($data);

        return response()->json(['ok' => true, 'id' => $task->id]);
    }

    public function update(Request $request, Task $task)
    {
        $data = $this->validated($request);
        $prevStatus = $task->status;

        $task->update($data);

        if ($prevStatus !== $data['status']) {
            $task->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'status_change',
                'text' => (Task::STATUSES[$prevStatus] ?? $prevStatus).' ➝ '.(Task::STATUSES[$data['status']] ?? $data['status']),
            ]);
        }

        return response()->json(['ok' => true, 'id' => $task->id]);
    }

    public function updateStatus(Request $request, Task $task)
    {
        $data = $request->validate([
            'status' => ['required', 'in:new,in_progress,waiting,done'],
        ]);

        $prevStatus = $task->status;

        if ($prevStatus !== $data['status']) {
            $task->update(['status' => $data['status']]);
            $task->comments()->create([
                'user_id' => $request->user()->id,
                'type' => 'status_change',
                'text' => (Task::STATUSES[$prevStatus] ?? $prevStatus).' ➝ '.(Task::STATUSES[$data['status']] ?? $data['status']),
            ]);
        }

        return response()->json(['ok' => true, 'id' => $task->id]);
    }

    public function destroy(Task $task)
    {
        foreach ($task->images as $image) {
            Storage::disk('public')->delete('task-images/'.$image->filename);
        }

        $task->delete();

        return response()->json(['ok' => true]);
    }

    public function exportCsv(): StreamedResponse
    {
        $tasks = Task::with('creator')->withCount('comments')->orderByDesc('created_at')->get();

        $filename = 'threadtrack-tasks-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $columns = [
            'ID', 'Title', 'Buyer', 'Style', 'Department', 'Sample Qty',
            'Status', 'Priority', 'Deadline', 'Overdue', 'Created By', 'Created At', 'Updates',
        ];

        return response()->streamDownload(function () use ($tasks, $columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            foreach ($tasks as $task) {
                fputcsv($out, [
                    $task->id,
                    $task->title,
                    $task->buyer,
                    $task->style,
                    $task->department,
                    $task->sample_qty,
                    $task->statusLabel(),
                    $task->priorityLabel(),
                    $task->deadline?->format('Y-m-d'),
                    $task->isOverdue() ? 'Yes' : 'No',
                    $task->creator?->name,
                    $task->created_at?->format('Y-m-d H:i'),
                    $task->comments_count,
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'buyer' => ['nullable', 'string', 'max:150'],
            'style' => ['nullable', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:150'],
            'sample_qty' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:new,in_progress,waiting,done'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'deadline' => ['nullable', 'date'],
        ]);

        $validated['deadline'] = $validated['deadline'] ?? null;
        $validated['sample_qty'] = $validated['sample_qty'] ?? null;
        if (! empty($validated['description'])) {
            $validated['description'] = strip_tags($validated['description'], self::ALLOWED_RICH_TEXT_TAGS);
        }

        return $validated;
    }

    private function transform(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'buyer' => $task->buyer,
            'style' => $task->style,
            'department' => $task->department,
            'sample_qty' => $task->sample_qty,
            'status' => $task->status,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->format('Y-m-d'),
            'created_by_name' => $task->creator?->name,
            'created_at' => $task->created_at?->toIso8601String(),
            'comment_count' => $task->comments_count,
            'images' => $task->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $img->url(),
            ])->values(),
        ];
    }
}
