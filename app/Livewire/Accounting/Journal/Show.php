<?php

namespace App\Livewire\Accounting\Journal;

use App\Livewire\BaseComponent;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

class Show extends BaseComponent
{
    public JournalEntry $entry;

    public array $entryData = [];

    public array $lines = [];

    public bool $canReverse = false;

    public bool $isLoading = false;

    public function mount(JournalEntry $entry): void
    {
        $this->entry = $entry->load(['lines.account', 'creator', 'approver', 'poster', 'reverser']);
        $this->loadEntryData();
    }

    protected function loadEntryData(): void
    {
        $this->entryData = [
            'id' => $this->entry->id,
            'entry_number' => 'JE-'.str_pad($this->entry->id, 6, '0', STR_PAD_LEFT),
            'entry_date' => $this->entry->entry_date?->format('d M Y') ?? 'N/A',
            'entry_date_raw' => $this->entry->entry_date?->toDateString() ?? '',
            'description' => $this->entry->description,
            'status' => $this->entry->status?->value ?? 'Draft',
            'status_label' => $this->entry->status?->label() ?? 'Draft',
            'status_color' => $this->entry->status?->color() ?? 'badge-default',
            'reference_type' => $this->entry->reference_type,
            'reference_id' => $this->entry->reference_id,
            'total_debits' => $this->entry->getTotalDebits(),
            'total_credits' => $this->entry->getTotalCredits(),
            'is_balanced' => $this->entry->isBalanced(),
            'is_posted' => $this->entry->isPosted(),
            'is_draft' => $this->entry->isDraft(),
            'is_pending' => $this->entry->isPending(),
            'is_reversed' => $this->entry->isReversed(),
            'is_rejected' => $this->entry->isRejected(),
            'created_at' => $this->entry->created_at?->format('d M Y, H:i') ?? 'N/A',
            'posted_at' => $this->entry->posted_at?->format('d M Y, H:i') ?? null,
            'creator' => $this->entry->creator ? [
                'id' => $this->entry->creator->id,
                'name' => $this->entry->creator->name,
            ] : null,
            'poster' => $this->entry->postedBy ? [
                'id' => $this->entry->postedBy->id,
                'name' => $this->entry->postedBy->name,
            ] : null,
            'approver' => $this->entry->approver ? [
                'id' => $this->entry->approver->id,
                'name' => $this->entry->approver->name,
            ] : null,
            'reverser' => $this->entry->reversedBy ? [
                'id' => $this->entry->reversedBy->id,
                'name' => $this->entry->reversedBy->name,
            ] : null,
        ];

        $this->lines = $this->entry->lines->map(function ($line) {
            return [
                'id' => $line->id,
                'account_code' => $line->account_code,
                'account_name' => $line->account->account_name ?? 'N/A',
                'account_type' => $line->account->account_type ?? 'Unknown',
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
                'is_debit' => $line->isDebit(),
                'is_credit' => $line->isCredit(),
            ];
        })->toArray();

        $this->canReverse = $this->entry->isPosted() && ! $this->entry->isReversed();
    }

    public function reverse(string $reason = ''): ?Redirector
    {
        if (! $this->canReverse) {
            $this->error('This entry cannot be reversed.');

            return null;
        }

        if (empty($reason)) {
            $this->addError('reverse_reason', 'Reason is required for reversal');

            return null;
        }

        $this->isLoading = true;

        try {
            $accountingService = app(AccountingService::class);
            $accountingService->reverseJournalEntry($this->entry, $reason);

            $this->success('Journal entry reversed successfully!');

            // Refresh the entry
            $this->entry = $this->entry->fresh()->load(['lines.account', 'creator', 'approver', 'poster', 'reverser']);
            $this->loadEntryData();

            return null;
        } catch (\Exception $e) {
            $this->error('Failed to reverse entry: '.$e->getMessage());

            return null;
        } finally {
            $this->isLoading = false;
        }
    }

    public function render(): View
    {
        return view('livewire.accounting.journal.show');
    }
}
