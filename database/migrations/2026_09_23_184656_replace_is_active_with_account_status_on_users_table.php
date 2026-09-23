<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_status', ['pending', 'active', 'disabled'])
                ->default('active')
                ->after('role');
        });

        DB::table('users')
            ->where('is_active', false)
            ->update(['account_status' => 'disabled']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'is_active']);
            $table->dropColumn('is_active');
            $table->string('password')->nullable()->change();
            $table->index(['role', 'account_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNull('password')
            ->update(['password' => Hash::make(Str::random(64))]);

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });

        DB::table('users')
            ->where('account_status', '!=', 'active')
            ->update(['is_active' => false]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'account_status']);
            $table->dropColumn('account_status');
            $table->string('password')->nullable(false)->change();
            $table->index(['role', 'is_active']);
        });
    }
};
