<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\TransactionApprovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionApprovedNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transaction_approved_email_renders_correctly(): void
    {
        $teller = User::factory()->create(['role' => 'teller']);
        $approver = User::factory()->create(['role' => 'manager']);
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'approved_by' => $approver->id,
            'status' => TransactionStatus::Approved,
        ]);

        $notification = new TransactionApprovedNotification($transaction);
        $mail = $notification->toMail($teller);

        $this->assertStringContainsString('Transaction Approved', $mail->subject);
        $this->assertStringContainsString((string) $transaction->id, (string) $mail->render());
    }

    #[Test]
    public function notification_sent_to_teller_on_approval(): void
    {
        Notification::fake();

        $teller = User::factory()->create(['role' => 'teller']);
        $approver = User::factory()->create(['role' => 'manager']);
        $transaction = Transaction::factory()->create([
            'user_id' => $teller->id,
            'approved_by' => $approver->id,
            'status' => TransactionStatus::Approved,
        ]);

        Notification::send([$teller], new TransactionApprovedNotification($transaction));

        Notification::assertSentTo(
            [$teller],
            TransactionApprovedNotification::class,
            function ($notification, $channels) use ($transaction) {
                return $notification->transaction->id === $transaction->id;
            }
        );
    }
}
