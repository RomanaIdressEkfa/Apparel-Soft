<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('season')->nullable()->after('style');
            $table->string('po_number')->nullable()->after('season');
            $table->string('sample_stage')->nullable()->after('sample_qty');
            $table->unsignedInteger('order_qty')->nullable()->after('sample_stage');
            $table->decimal('unit_price', 10, 2)->nullable()->after('order_qty');
            $table->string('fabric')->nullable()->after('unit_price');
            $table->string('color')->nullable()->after('fabric');
            $table->date('received_at')->nullable()->after('color');
            $table->date('ship_date')->nullable()->after('deadline');

            $table->index('season');
            $table->index('ship_date');
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['season']);
            $table->dropIndex(['ship_date']);
            $table->dropIndex(['received_at']);
            $table->dropColumn([
                'season', 'po_number', 'sample_stage', 'order_qty',
                'unit_price', 'fabric', 'color', 'received_at', 'ship_date',
            ]);
        });
    }
};
