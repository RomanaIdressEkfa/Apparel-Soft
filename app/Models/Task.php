<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    public const STATUSES = [
        'new' => 'New',
        'in_progress' => 'In Progress',
        'waiting' => 'Waiting',
        'done' => 'Done',
        'cancelled' => 'Cancelled',
    ];

    /** Stages a task moves through, in order. 'cancelled' sits outside this flow. */
    public const STAGE_FLOW = ['new', 'in_progress', 'waiting', 'done'];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'urgent' => 'Urgent',
    ];

    public const SAMPLE_STAGES = [
        'proto' => 'Proto Sample',
        'fit' => 'Fit Sample',
        'sms' => 'SMS / Salesman',
        'size_set' => 'Size Set',
        'pp' => 'PP Sample',
        'top' => 'TOP Sample',
        'shipment' => 'Shipment Sample',
    ];

    /** Columns of the Sample Tracking List, in sheet order. */
    public const SHEET_FIELDS = [
        'season', 'dept', 'title', 'thread', 'spec', 'button_rivet', 'wash_detail',
        'fab_art', 'received_at', 'sample_type', 'techpack_handover_date',
        'cutting_status', 'sewing_status', 'wash_send_date', 'wash_rcvd_date',
        'sample_submit_date', 'price_note', 'booking_fabric', 'booking_body_thread',
        'booking_emb_thread', 'booking_metalwork', 'booking_lace', 'remarks',
    ];

    public const MILESTONE_STATES = ['', 'Done', 'Running', 'N/A'];

    protected $fillable = [
        'title',
        'description',
        'buyer',
        'style',
        'season',
        'dept',
        'po_number',
        'department',
        'sample_qty',
        'sample_stage',
        'sample_type',
        'order_qty',
        'unit_price',
        'fabric',
        'fab_art',
        'color',
        'thread',
        'spec',
        'button_rivet',
        'wash_detail',
        'received_at',
        'techpack_handover_date',
        'cutting_status',
        'sewing_status',
        'wash_send_date',
        'wash_rcvd_date',
        'sample_submit_date',
        'price_note',
        'booking_fabric',
        'booking_body_thread',
        'booking_emb_thread',
        'booking_metalwork',
        'booking_lace',
        'remarks',
        'row_state',
        'status',
        'priority',
        'deadline',
        'ship_date',
        'created_by',
    ];

    protected $casts = [
        'deadline' => 'date',
        'ship_date' => 'date',
        'received_at' => 'date',
        'techpack_handover_date' => 'date',
        'wash_send_date' => 'date',
        'wash_rcvd_date' => 'date',
        'sample_submit_date' => 'date',
        'unit_price' => 'decimal:2',
    ];

    /** The four lines the sheet packs into its "Rcvd status" cell. */
    public function rcvdStatusLines(): array
    {
        return array_filter([
            $this->thread ? 'Thread: '.$this->thread : null,
            $this->spec ? 'Spec: '.$this->spec : null,
            $this->button_rivet ? 'Button and Rivet: '.$this->button_rivet : null,
            $this->wash_detail ? 'Wash: '.$this->wash_detail : null,
        ]);
    }

    /** The five lines the sheet packs into its "Booking Status" cell. */
    public function bookingStatusLines(): array
    {
        return array_filter([
            $this->booking_fabric ? 'Fabric: '.$this->booking_fabric : null,
            $this->booking_body_thread ? 'Body Thread: '.$this->booking_body_thread : null,
            $this->booking_emb_thread ? 'EMB Thread: '.$this->booking_emb_thread : null,
            $this->booking_metalwork ? 'Metalwork: '.$this->booking_metalwork : null,
            $this->booking_lace ? 'Lace: '.$this->booking_lace : null,
        ]);
    }

    public const ROW_STATES = [
        'active' => 'Active',
        'hold' => 'Hold',
        'drop' => 'Drop',
    ];

    public function isDropped(): bool
    {
        return $this->row_state === 'drop';
    }

    public function orderValue(): ?float
    {
        if ($this->order_qty === null || $this->unit_price === null) {
            return null;
        }

        return round($this->order_qty * (float) $this->unit_price, 2);
    }

    public function sampleStageLabel(): ?string
    {
        return $this->sample_stage ? (self::SAMPLE_STAGES[$this->sample_stage] ?? $this->sample_stage) : null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function images(): HasMany
    {
        return $this->hasMany(TaskImage::class)->whereNull('task_comment_id')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    public function isOverdue(): bool
    {
        return $this->deadline !== null
            && ! in_array($this->status, ['done', 'cancelled'], true)
            && $this->deadline->isPast()
            && ! $this->deadline->isToday();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }
}
