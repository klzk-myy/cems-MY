<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\Transaction;
use App\Services\Transaction\ReceiptGenerationService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Tests\TestCase;

class ReceiptGenerationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_pdf_response_for_completed_transaction(): void
    {
        $transaction = Transaction::factory()->completed()->create();

        // PDF forwards setPaper through __call magic, so Mockery (which allows
        // expectations on magic methods) is used instead of PHPUnit's mock
        // builder, whose addMethods() is deprecated.
        $pdf = Mockery::mock(PDF::class);
        $pdf->shouldReceive('loadView')
            ->once()
            ->andReturnUsing(function ($view, $data) use ($pdf, $transaction) {
                $this->assertSame('transactions.receipt', $view);
                $this->assertSame($transaction->id, $data['transaction']->id);
                $this->assertArrayHasKey('barcodeImage', $data);
                $this->assertArrayHasKey('qrCodeImage', $data);
                $this->assertArrayHasKey('barcodeText', $data);

                return $pdf;
            });
        $pdf->shouldReceive('setPaper')->once()->with([0, 0, 226.77, 841.89], 'portrait')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(new Response('pdf-content'));

        $barcodeGenerator = $this->createMock(BarcodeGeneratorPNG::class);
        $barcodeGenerator->method('getBarcode')->willReturn('barcode-bytes');

        $service = new ReceiptGenerationService($pdf, $barcodeGenerator);
        $response = $service->generate($transaction);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('pdf-content', $response->getContent());
    }

    #[Test]
    public function it_handles_barcode_generation_failure_gracefully(): void
    {
        $transaction = Transaction::factory()->completed()->create();

        $pdf = Mockery::mock(PDF::class);
        $pdf->shouldReceive('loadView')
            ->once()
            ->andReturnUsing(function ($view, $data) use ($pdf) {
                $this->assertSame('transactions.receipt', $view);
                $this->assertNull($data['barcodeImage']);

                return $pdf;
            });
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->andReturn(new Response('pdf-content'));

        $barcodeGenerator = $this->createMock(BarcodeGeneratorPNG::class);
        $barcodeGenerator->method('getBarcode')->willThrowException(new \Exception('Barcode failed'));

        $service = new ReceiptGenerationService($pdf, $barcodeGenerator);
        $response = $service->generate($transaction);

        $this->assertInstanceOf(Response::class, $response);
    }

    #[Test]
    public function receipt_view_renders_transaction_details(): void
    {
        $transaction = Transaction::factory()->completed()->create()
            ->load(['customer', 'user', 'approver']);

        $html = view('transactions.receipt', [
            'transaction' => $transaction,
            'barcodeImage' => null,
            'qrCodeImage' => null,
            'barcodeText' => str_pad((string) $transaction->id, 10, '0', STR_PAD_LEFT),
        ])->render();

        $this->assertStringContainsString($transaction->reference, $html);
        $this->assertStringContainsString($transaction->currency_code, $html);
        $this->assertStringContainsString('RM', $html);
        $this->assertStringContainsString('Transaction Receipt', $html);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
