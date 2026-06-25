<?php

namespace App\Console\Commands;

use App\Models\SanctionList;
use Illuminate\Console\Command;

class SanctionsStatus extends Command
{
    protected $signature = 'sanctions:status
                            {--list= : Show details for specific list}';

    protected $description = 'Show status of sanctions lists and their last update times';

    public function handle(): int
    {
        $listName = $this->option('list');

        if ($listName) {
            return $this->showListDetails($listName);
        }

        return $this->showAllLists();
    }

    protected function showAllLists(): int
    {
        $this->info('Sanctions Lists Status');
        $this->newLine();

        $lists = SanctionList::orderBy('list_type')->get();

        if ($lists->isEmpty()) {
            $this->warn('No sanctions lists configured.');
            $this->line('Run "php artisan sanctions:update" to initialize.');

            return Command::SUCCESS;
        }

        $rows = $lists->map(function ($list) {
            return [
                $list->name,
                $list->list_type,
                $list->entry_count,
                $list->last_updated_at ? $list->last_updated_at->format('Y-m-d H:i') : 'Never',
                $this->formatStatus($list->update_status),
                $list->isAutoUpdated() ? 'Auto' : 'Manual',
            ];
        })->toArray();

        $this->table(
            ['Name', 'Type', 'Entries', 'Last Updated', 'Status', 'Source'],
            $rows
        );

        $this->newLine();

        // Summary statistics
        $totalEntries = $lists->sum('entry_count');
        $failedUpdates = $lists->where('update_status', 'failed')->count();
        $neverRun = $lists->where('update_status', 'never_run')->count();

        $this->info('Summary:');
        $this->line("  Total Lists: {$lists->count()}");
        $this->line("  Total Entries: {$totalEntries}");
        $this->line("  Failed Updates: {$failedUpdates}");
        $this->line("  Never Updated: {$neverRun}");

        if ($failedUpdates > 0) {
            $this->newLine();
            $this->warn('Some lists have failed updates. Run "php artisan sanctions:update" to retry.');
        }

        return Command::SUCCESS;
    }

    protected function showListDetails(string $name): int
    {
        $list = SanctionList::where('name', 'like', "%{$name}%")
            ->orWhere('list_type', $name)
            ->first();

        if (! $list) {
            $this->error("List not found: {$name}");

            return Command::FAILURE;
        }

        $this->info("Details: {$list->name}");
        $this->newLine();

        $details = [
            ['ID', $list->id],
            ['Type', $list->list_type],
            ['Source URL', $list->source_url ?? 'N/A'],
            ['Source Format', $list->source_format ?? 'N/A'],
            ['Active', $list->is_active ? 'Yes' : 'No'],
            ['Entries', $list->entry_count],
            ['Last Updated', $list->last_updated_at ? $list->last_updated_at->format('Y-m-d H:i:s') : 'Never'],
            ['Last Attempted', $list->last_attempted_at ? $list->last_attempted_at->format('Y-m-d H:i:s') : 'Never'],
            ['Update Status', $this->formatStatus($list->update_status)],
            ['Checksum', $list->last_checksum ? substr($list->last_checksum, 0, 16).'...' : 'N/A'],
            ['Auto Updated', $list->isAutoUpdated() ? 'Yes' : 'No'],
        ];

        $this->table(['Property', 'Value'], $details);

        if ($list->last_error_message) {
            $this->newLine();
            $this->error('Last Error:');
            $this->line($list->last_error_message);
        }

        return Command::SUCCESS;
    }

    protected function formatStatus(?string $status): string
    {
        return match ($status) {
            'success' => '<fg=green>Success</>',
            'failed' => '<fg=red>Failed</>',
            'pending' => '<fg=yellow>Pending</>',
            'never_run' => '<fg=gray>Never Run</>',
            default => '<fg=gray>Unknown</>',
        };
    }
}
