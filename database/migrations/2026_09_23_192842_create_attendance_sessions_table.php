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
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained()
                ->restrictOnDelete();
            $table->date('work_date');
            $table->dateTime('time_in_at');
            $table->dateTime('time_out_at')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['employee_id', 'time_out_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
