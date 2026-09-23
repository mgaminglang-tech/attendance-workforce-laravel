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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->restrictOnDelete();
            $table->string('employee_number', 50)->unique();
            $table->foreignId('department_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->string('job_title')->nullable();
            $table->enum('employment_status', ['active', 'inactive'])->default('active');
            $table->date('hired_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
