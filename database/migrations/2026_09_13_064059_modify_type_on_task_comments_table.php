<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE task_comments MODIFY type ENUM('comment','status_change','correction','buyer_email','reply') NOT NULL DEFAULT 'comment'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE task_comments MODIFY type ENUM('comment','status_change') NOT NULL DEFAULT 'comment'");
    }
};
