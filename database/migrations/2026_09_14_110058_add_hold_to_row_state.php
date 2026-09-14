<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tasks MODIFY row_state ENUM('active','hold','drop') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("UPDATE tasks SET row_state = 'active' WHERE row_state = 'hold'");
        DB::statement("ALTER TABLE tasks MODIFY row_state ENUM('active','drop') NOT NULL DEFAULT 'active'");
    }
};
