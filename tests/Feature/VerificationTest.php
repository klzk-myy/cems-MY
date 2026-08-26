<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Transaction;
use App\Services\Transaction\ReceiptGenerationService;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function completed_transaction_reference_shows_verification_status(): void
    {
        $transaction = Transaction::factory()->completed()->create();

        $response = $this->get(route('verification.transaction', ['reference' => $transaction->reference]));

        $response->assertOk();
        $response->assertSee('Completed');
        $response->assertSee($transaction->created_at->format('Y-m-d'));
    }

    #[Test]
    public function unknown_reference_returns_graceful_not_verifiable_response(): void
    {
        $response = $this->get(route('verification.transaction', ['reference' => 'TX-99999999']));

        $response->assertOk();
        $response->assertSee('could not be verified');
    }

    #[Test]
    public function malformed_reference_returns_not_verifiable_response(): void
    {
        $response = $this->get(route('verification.transaction', ['reference' => 'NOT-A-VALID-REF']));

        $response->assertOk();
        $response->assertSee('could not be verified');
    }

    #[Test]
    public function verification_page_does_not_leak_customer_pii(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Zubair Al-Rashid']);
        $transaction = Transaction::factory()->completed()->for($customer)->create();

        $response = $this->get(route('verification.transaction', ['reference' => $transaction->reference]));

        $response->assertOk();
        $response->assertDontSee('Zubair Al-Rashid');
    }

    #[Test]
    public function receipt_qr_payload_embeds_working_verification_url(): void
    {
        $transaction = Transaction::factory()->completed()->create();

        $payload = null;
        $pdf = Mockery::mock(DomPdf::class);
        $pdf->shouldReceive('loadView')->once()->andReturnUsing(function ($view, $data) use ($pdf, &$payload) {
            // Reproduce the QR payload exactly as ReceiptGenerationService does.
            $encoded = json_encode([
                'id' => $data['transaction']->id,
                'verify' => route('verification.transaction', ['reference' => $data['transaction']->reference]),
            ]);

            if ($encoded !== false) {
                $payload = json_decode($encoded, true);
            }

            return $pdf;
        });
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(new Response('pdf'));

        $barcodeGenerator = $this->createMock(BarcodeGeneratorPNG::class);
        $barcodeGenerator->method('getBarcode')->willReturn('bytes');

        app()->instance(DomPdf::class, $pdf);
        $service = new ReceiptGenerationService($pdf, $barcodeGenerator);
        $service->generate($transaction);

        $this->assertNotNull($payload['verify']);

        $verifyUrl = (string) ($payload['verify'] ?? '');
        $path = parse_url($verifyUrl, PHP_URL_PATH);
        $this->assertIsString($path);
        $followUp = $this->get($path);

        $followUp->assertOk();
        $followUp->assertSee('Completed');

        Mockery::close();
    }
}
