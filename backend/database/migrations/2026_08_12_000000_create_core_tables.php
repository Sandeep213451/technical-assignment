<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['Admin', 'Manager', 'User'])->default('User');
            $table->string('department');
            $table->integer('years_of_experience')->default(0);
            $table->string('location');
            $table->integer('active_tasks_count')->default(0);
            $table->rememberToken();
            $table->timestamps();

            // Composite index for fast rule evaluation queries
            $table->index(['department', 'active_tasks_count', 'years_of_experience'], 'idx_user_rule_evaluation');
            $table->index('location', 'idx_user_location');
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('status')->default('Todo');
            $table->string('priority')->default('Medium');
            $table->date('due_date');
            $table->unsignedBigInteger('created_by');
            $table->json('rules')->nullable();
            $table->timestamps();
            
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->index('status', 'idx_task_status');
            $table->index('priority', 'idx_task_priority');
        });

        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('assigned_at');
            
            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->index(['task_id', 'user_id'], 'idx_task_user');
            $table->index('user_id', 'idx_assignment_user');
        });
    }

    public function down()
    {
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('users');
    }
};

