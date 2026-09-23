<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('attendance_adjustments', function (Blueprint $table) {
            $values = ['work_from_home', 'office_based', 'field_based'];

            $table->enum('before_work_arrangement', $values)
                ->nullable()
                ->after('previous_time_out_at');
            $table->enum('after_work_arrangement', $values)
                ->nullable()
                ->after('corrected_time_out_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_adjustments', function (Blueprint $table) {
            $table->dropColumn(['before_work_arrangement', 'after_work_arrangement']);
        });
    }
};
