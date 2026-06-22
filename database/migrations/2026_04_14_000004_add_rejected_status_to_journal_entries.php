<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Update any entries with null status to 'Draft'
        try {
            DB::statement("UPDATE journal_entries SET status = 'Draft' WHERE status IS NULL");
        } catch (Exception $e) {
            // Table might not exist yet; ignore.
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('Draft', 'Pending', 'Posted', 'Reversed', 'Rejected') DEFAULT 'Draft'");
        } elseif ($driver === 'sqlite') {
            // SQLite: Recreate table to modify the CHECK constraint to include 'Rejected'
            Schema::disableForeignKeyConstraints();

            // Backup data
            DB::statement('CREATE TABLE journal_entries_backup AS SELECT * FROM journal_entries');

            // Drop existing table
            Schema::dropIfExists('journal_entries');

            // Recreate table with updated schema (including 'Rejected' in status CHECK)
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('period_id')->nullable()->after('id')->constrained('accounting_periods')->nullOnDelete();
                $table->string('entry_number', 20)->unique()->nullable();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->date('entry_date');
                $table->string('reference_type', 50);
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description');
                $table->enum('status', ['Draft', 'Pending', 'Posted', 'Reversed', 'Rejected'])->default('Draft');
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_notes')->nullable();
                $table->foreignId('posted_by')->constrained('users');
                $table->timestamp('posted_at')->useCurrent();
                $table->foreignId('reversed_by')->nullable()->constrained('users');
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
                $table->foreignId('department_id')->nullable()->constrained('departments');
                $table->timestamps();

                $table->index('entry_date');
                $table->index(['reference_type', 'reference_id']);
                $table->index('status');
                $table->index('entry_number');
                $table->index('period_id');
            });

            // Ensure all statuses in backup are valid
            DB::table('journal_entries_backup')->whereNotIn('status', ['Draft', 'Pending', 'Posted', 'Reversed', 'Rejected'])
                ->update(['status' => 'Draft']);

            // Restore data
            DB::statement('INSERT INTO journal_entries SELECT * FROM journal_entries_backup');

            // Cleanup
            DB::statement('DROP TABLE journal_entries_backup');

            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE journal_entries MODIFY COLUMN status ENUM('Draft', 'Pending', 'Posted', 'Reversed') DEFAULT 'Posted'");
        } elseif ($driver === 'sqlite') {
            Schema::disableForeignKeyConstraints();

            DB::statement('CREATE TABLE journal_entries_backup AS SELECT * FROM journal_entries');
            Schema::dropIfExists('journal_entries');

            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('period_id')->nullable()->after('id')->constrained('accounting_periods')->nullOnDelete();
                $table->string('entry_number', 20)->unique()->nullable();
                $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
                $table->date('entry_date');
                $table->string('reference_type', 50);
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->text('description');
                $table->enum('status', ['Draft', 'Pending', 'Posted', 'Reversed'])->default('Posted');
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('approved_by')->nullable()->constrained('users');
                $table->timestamp('approved_at')->nullable();
                $table->text('approval_notes')->nullable();
                $table->foreignId('posted_by')->constrained('users');
                $table->timestamp('posted_at')->useCurrent();
                $table->foreignId('reversed_by')->nullable()->constrained('users');
                $table->timestamp('reversed_at')->nullable();
                $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers');
                $table->foreignId('department_id')->nullable()->constrained('departments');
                $table->timestamps();

                $table->index('entry_date');
                $table->index(['reference_type', 'reference_id']);
                $table->index('status');
                $table->index('entry_number');
                $table->index('period_id');
            });

            // Convert Rejected to Draft for downgrade
            DB::table('journal_entries_backup')->where('status', 'Rejected')
                ->update(['status' => 'Draft']);

            DB::statement('INSERT INTO journal_entries SELECT * FROM journal_entries_backup');
            DB::statement('DROP TABLE journal_entries_backup');

            Schema::enableForeignKeyConstraints();
        }
    }
};
