<?php

namespace App\Http\Controllers;

use App\Exports\SampleTrackingSheet;
use App\Models\Buyer;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
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
            'status' => ['required', 'in:new,in_progress,waiting,done,cancelled'],
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

    /** The workbook the merchandiser actually works in — same columns, colours and photos. */
    public function exportSheet(): StreamedResponse
    {
        $tasks = Task::with('images')->orderBy('received_at')->orderBy('id')->get();

        $book = (new SampleTrackingSheet($tasks))->build();
        $filename = 'Sample Tracking List '.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($book) {
            $writer = new Xlsx($book);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportCsv(): StreamedResponse
    {
        $tasks = Task::with(['creator', 'images', 'comments.author'])
            ->orderByDesc('created_at')
            ->get();

        $buyerDirectory = Buyer::all()->keyBy('name');

        $filename = 'apparel-soft-track-tasks-'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ];

        $columns = [
            'ID', 'Title', 'Status', 'Priority',
            'Buyer', 'Buyer Brand', 'Buyer Contact', 'Buyer Email', 'Buyer Phone', 'Buyer Country', 'Buyer Notes',
            'Style', 'Season', 'PO Number', 'Fabric', 'Color',
            'Order Qty', 'Unit Price', 'Order Value',
            'Sample Stage', 'Sample Qty', 'Department',
            'Received On', 'Deadline', 'Ship Date', 'Days To Deadline', 'Overdue',
            'Details', 'Photos', 'Updates', 'Last Update By', 'Last Update On', 'Last Update',
            'Created By', 'Created At', 'Last Edited At',
        ];

        return response()->streamDownload(function () use ($tasks, $columns, $buyerDirectory) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);

            foreach ($tasks as $task) {
                $buyer = $buyerDirectory->get($task->buyer);
                $lastComment = $task->comments->sortByDesc('created_at')->first();

                $daysToDeadline = $task->deadline
                    ? now()->startOfDay()->diffInDays($task->deadline->startOfDay(), false)
                    : null;

                fputcsv($out, [
                    $task->id,
                    $task->title,
                    $task->statusLabel(),
                    $task->priorityLabel(),
                    $task->buyer,
                    $buyer?->brand,
                    $buyer?->contact_person,
                    $buyer?->email,
                    $buyer?->phone,
                    $buyer?->country,
                    $this->toPlainText($buyer?->notes),
                    $task->style,
                    $task->season,
                    $task->po_number,
                    $task->fabric,
                    $task->color,
                    $task->order_qty,
                    $task->unit_price,
                    $task->orderValue(),
                    $task->sampleStageLabel(),
                    $task->sample_qty,
                    $task->department,
                    $task->received_at?->format('Y-m-d'),
                    $task->deadline?->format('Y-m-d'),
                    $task->ship_date?->format('Y-m-d'),
                    $daysToDeadline,
                    $task->isOverdue() ? 'Yes' : 'No',
                    $this->toPlainText($task->description),
                    $task->images->count(),
                    $task->comments->count(),
                    $lastComment?->author?->name,
                    $lastComment?->created_at?->format('Y-m-d H:i'),
                    $this->toPlainText($lastComment?->text),
                    $task->creator?->name,
                    $task->created_at?->format('Y-m-d H:i'),
                    $task->updated_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $filename, $headers);
    }

    /** Flattens stored rich text into something a spreadsheet cell can show. */
    private function toPlainText(?string $html): string
    {
        if ($html === null || $html === '') {
            return '';
        }

        $withBreaks = preg_replace('/<\/(p|div|li|h[1-6]|blockquote)>/i', ' ', $html);
        $text = html_entity_decode(strip_tags((string) $withBreaks), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'buyer' => ['nullable', 'string', 'max:150'],
            'style' => ['nullable', 'string', 'max:150'],
            'season' => ['nullable', 'string', 'max:60'],
            'dept' => ['nullable', 'string', 'max:30'],
            'po_number' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:150'],
            'fab_art' => ['nullable', 'string', 'max:190'],
            'sample_type' => ['nullable', 'string', 'max:60'],
            'thread' => ['nullable', 'string', 'max:2000'],
            'spec' => ['nullable', 'string', 'max:2000'],
            'button_rivet' => ['nullable', 'string', 'max:2000'],
            'wash_detail' => ['nullable', 'string', 'max:2000'],
            'techpack_handover_date' => ['nullable', 'date'],
            'cutting_status' => ['nullable', 'string', 'max:60'],
            'sewing_status' => ['nullable', 'string', 'max:60'],
            'wash_send_date' => ['nullable', 'date'],
            'wash_rcvd_date' => ['nullable', 'date'],
            'sample_submit_date' => ['nullable', 'date'],
            'price_note' => ['nullable', 'string', 'max:120'],
            'booking_fabric' => ['nullable', 'string', 'max:190'],
            'booking_body_thread' => ['nullable', 'string', 'max:190'],
            'booking_emb_thread' => ['nullable', 'string', 'max:190'],
            'booking_metalwork' => ['nullable', 'string', 'max:190'],
            'booking_lace' => ['nullable', 'string', 'max:190'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'row_state' => ['nullable', 'in:active,drop'],
            'sample_qty' => ['nullable', 'integer', 'min:0'],
            'sample_stage' => ['nullable', 'in:'.implode(',', array_keys(Task::SAMPLE_STAGES))],
            'order_qty' => ['nullable', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'fabric' => ['nullable', 'string', 'max:200'],
            'color' => ['nullable', 'string', 'max:150'],
            'received_at' => ['nullable', 'date'],
            'status' => ['required', 'in:new,in_progress,waiting,done,cancelled'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'deadline' => ['nullable', 'date'],
            'ship_date' => ['nullable', 'date'],
        ]);

        $nullables = [
            'deadline', 'ship_date', 'received_at', 'sample_qty', 'sample_stage', 'order_qty', 'unit_price',
            'techpack_handover_date', 'wash_send_date', 'wash_rcvd_date', 'sample_submit_date',
        ];
        foreach ($nullables as $nullable) {
            $validated[$nullable] = $validated[$nullable] ?? null;
        }
        $validated['row_state'] = $validated['row_state'] ?? 'active';
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
            'season' => $task->season,
            'dept' => $task->dept,
            'po_number' => $task->po_number,
            'department' => $task->department,
            'fab_art' => $task->fab_art,
            'sample_type' => $task->sample_type,
            'thread' => $task->thread,
            'spec' => $task->spec,
            'button_rivet' => $task->button_rivet,
            'wash_detail' => $task->wash_detail,
            'techpack_handover_date' => $task->techpack_handover_date?->format('Y-m-d'),
            'cutting_status' => $task->cutting_status,
            'sewing_status' => $task->sewing_status,
            'wash_send_date' => $task->wash_send_date?->format('Y-m-d'),
            'wash_rcvd_date' => $task->wash_rcvd_date?->format('Y-m-d'),
            'sample_submit_date' => $task->sample_submit_date?->format('Y-m-d'),
            'price_note' => $task->price_note,
            'booking_fabric' => $task->booking_fabric,
            'booking_body_thread' => $task->booking_body_thread,
            'booking_emb_thread' => $task->booking_emb_thread,
            'booking_metalwork' => $task->booking_metalwork,
            'booking_lace' => $task->booking_lace,
            'remarks' => $task->remarks,
            'row_state' => $task->row_state,
            'sample_qty' => $task->sample_qty,
            'sample_stage' => $task->sample_stage,
            'sample_stage_label' => $task->sampleStageLabel(),
            'order_qty' => $task->order_qty,
            'unit_price' => $task->unit_price === null ? null : (float) $task->unit_price,
            'order_value' => $task->orderValue(),
            'fabric' => $task->fabric,
            'color' => $task->color,
            'received_at' => $task->received_at?->format('Y-m-d'),
            'status' => $task->status,
            'priority' => $task->priority,
            'deadline' => $task->deadline?->format('Y-m-d'),
            'ship_date' => $task->ship_date?->format('Y-m-d'),
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
