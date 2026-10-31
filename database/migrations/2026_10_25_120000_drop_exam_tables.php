<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The exam/assignment system (exams, exam_users, exam_progress) was
     * removed from the codebase — drop the retired tables. Child tables
     * first so foreign keys do not block the drops.
     */
    public function up(): void
    {
        Schema::dropIfExists('exam_progress');
        Schema::dropIfExists('exam_users');
        Schema::dropIfExists('exams');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Table structures live in the original create migrations;
        // a rollback would re-run those instead of recreating them here.
    }
};
