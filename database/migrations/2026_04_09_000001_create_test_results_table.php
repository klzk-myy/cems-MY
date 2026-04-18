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
        Schema::create('test_results', function (Blueprint $table) {
            $table->id();
            $table->string('run_id', 64)->unique()->comment('Unique identifier for each test run');
            $table->string('test_suite', 100)->default('full')->comment('Test suite name: full, navigation, transaction, etc.');
            $table->integer('total_tests')->default(0)->comment('Total number of tests executed');
            $table->integer('passed')->default(0)->comment('Number of passed tests');
            $table->integer('failed')->default(0)->comment('Number of failed tests');
            $table->integer('skipped')->default(0)->comment('Number of skipped tests');
            $table->integer('assertions')->default(0)->comment('Total assertions count');
            $table->float('duration', 8, 2)->comment('Test duration in seconds');
            $table->enum('status', ['passed', 'failed', 'error', 'running'])->default('running')->comment('Overall test status');
            $table->longText('output')->nullable()->comment('Full test output');
            $table->longText('failures')->nullable()->comment('JSON array of failed tests with details');
            $table->longText('errors')->nullable()->comment('JSON array of error messages');
            $table->string('git_branch', 100)->nullable()->comment('Git branch when tests were run');
            $table->string('git_commit', 64)->nullable()->comment('Git commit hash');
            $table->string('executed_by', 100)->nullable()->comment('User who ran the tests');
            $table->timestamp('started_at')->nullable()->comment('When test run started');
            $table->timestamp('completed_at')->nullable()->comment('When test run completed');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index('status', 'idx_test_results_status');
            $table->index('test_suite', 'idx_test_results_suite');
            $table->index('created_at', 'idx_test_results_created');
            $table->index(['status', 'created_at'], 'idx_test_results_status_created');
        });

        // Note: Table comment added via Laravel's comment() method on individual columns above
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
