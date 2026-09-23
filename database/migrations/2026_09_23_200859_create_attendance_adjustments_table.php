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
        Schema::create('attendance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')
                ->constrained()
                ->restrictOnDelete();
            $table->foreignId('administrator_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('reason');
            $table->date('previous_work_date');
            $table->dateTime('previous_time_in_at');
            $table->dateTime('previous_time_out_at')->nullable();
            $table->date('corrected_work_date');
            $table->dateTime('corrected_time_in_at');
            $table->dateTime('corrected_time_out_at')->nullable();
            $table->dateTime('corrected_at');

            $table->index(['attendance_session_id', 'corrected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_adjustments');
    }
};
