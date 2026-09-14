<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mirrors the columns of the merchandiser's "Sample Tracking List" workbook. */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('dept')->nullable()->after('season');
            $table->string('fab_art')->nullable()->after('fabric');
            $table->string('sample_type')->nullable()->after('sample_stage');

            // "Rcvd status" block (one cell in the sheet, four lines inside it)
            $table->text('thread')->nullable()->after('color');
            $table->text('spec')->nullable()->after('thread');
            $table->text('button_rivet')->nullable()->after('spec');
            $table->text('wash_detail')->nullable()->after('button_rivet');

            // Production milestones
            $table->date('techpack_handover_date')->nullable()->after('received_at');
            $table->string('cutting_status')->nullable()->after('techpack_handover_date');
            $table->string('sewing_status')->nullable()->after('cutting_status');
            $table->date('wash_send_date')->nullable()->after('sewing_status');
            $table->date('wash_rcvd_date')->nullable()->after('wash_send_date');
            $table->date('sample_submit_date')->nullable()->after('wash_rcvd_date');
            $table->string('price_note')->nullable()->after('sample_submit_date');

            // "Booking Status" block (one cell in the sheet, five lines inside it)
            $table->string('booking_fabric')->nullable()->after('price_note');
            $table->string('booking_body_thread')->nullable()->after('booking_fabric');
            $table->string('booking_emb_thread')->nullable()->after('booking_body_thread');
            $table->string('booking_metalwork')->nullable()->after('booking_emb_thread');
            $table->string('booking_lace')->nullable()->after('booking_metalwork');

            $table->text('remarks')->nullable()->after('booking_lace');
            $table->enum('row_state', ['active', 'drop'])->default('active')->after('remarks');

            $table->index('dept');
            $table->index('row_state');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['dept']);
            $table->dropIndex(['row_state']);
            $table->dropColumn([
                'dept', 'fab_art', 'sample_type',
                'thread', 'spec', 'button_rivet', 'wash_detail',
                'techpack_handover_date', 'cutting_status', 'sewing_status',
                'wash_send_date', 'wash_rcvd_date', 'sample_submit_date', 'price_note',
                'booking_fabric', 'booking_body_thread', 'booking_emb_thread',
                'booking_metalwork', 'booking_lace', 'remarks', 'row_state',
            ]);
        });
    }
};
