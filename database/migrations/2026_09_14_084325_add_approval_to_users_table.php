<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_approved')->default(false)->after('password');
            $table->enum('role', ['admin', 'member'])->default('member')->after('is_approved');
            $table->timestamp('approved_at')->nullable()->after('role');
        });

        // Everyone who already had access keeps it; the first account becomes the admin.
        DB::table('users')->update(['is_approved' => true, 'approved_at' => now()]);

        $firstUserId = DB::table('users')->orderBy('id')->value('id');
        if ($firstUserId !== null) {
            DB::table('users')->where('id', $firstUserId)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_approved', 'role', 'approved_at']);
        });
    }
};
