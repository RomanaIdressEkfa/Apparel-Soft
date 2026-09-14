<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('new','in_progress','waiting','done','cancelled') NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        DB::statement("UPDATE tasks SET status = 'done' WHERE status = 'cancelled'");
        DB::statement("ALTER TABLE tasks MODIFY status ENUM('new','in_progress','waiting','done') NOT NULL DEFAULT 'new'");
    }
};
