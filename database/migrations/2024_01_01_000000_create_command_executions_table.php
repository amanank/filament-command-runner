<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('command_executions', function (Blueprint $table) {
            $table->id();
            $table->string('command');
            $table->json('options')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->integer('exit_code')->nullable();
            $table->longText('output')->nullable();
            $table->decimal('execution_time', 8, 2)->nullable();
            $table->string('environment', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['command', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('environment');
        });
    }

    public function down(): void {
        Schema::dropIfExists('command_executions');
    }
};
