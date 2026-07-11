<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\Transaction;
use App\Services\Transaction\ReceiptGenerationService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
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

        $pdf = $this->getMockBuilder(PDF::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadView', 'download'])
            ->addMethods(['setPaper'])
            ->getMock();

        $pdf->expects($this->once())
            ->method('loadView')
            ->willReturnCallback(function ($view, $data) use ($pdf, $transaction) {
                $this->assertSame('transactions.receipt', $view);
                $this->assertSame($transaction->id, $data['transaction']->id);
                $this->assertArrayHasKey('barcodeImage', $data);
                $this->assertArrayHasKey('qrCodeImage', $data);
                $this->assertArrayHasKey('barcodeText', $data);

                return $pdf;
            });
        $pdf->expects($this->once())->method('setPaper')->with([0, 0, 226.77, 841.89], 'portrait')->willReturnSelf();
        $pdf->expects($this->once())->method('download')->willReturn(new Response('pdf-content'));

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

        $pdf = $this->getMockBuilder(PDF::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['loadView', 'download'])
            ->addMethods(['setPaper'])
            ->getMock();

        $pdf->expects($this->once())
            ->method('loadView')
            ->willReturnCallback(function ($view, $data) use ($pdf) {
                $this->assertSame('transactions.receipt', $view);
                $this->assertNull($data['barcodeImage']);

                return $pdf;
            });
        $pdf->expects($this->once())->method('setPaper')->willReturnSelf();
        $pdf->method('download')->willReturn(new Response('pdf-content'));

        $barcodeGenerator = $this->createMock(BarcodeGeneratorPNG::class);
        $barcodeGenerator->method('getBarcode')->willThrowException(new \Exception('Barcode failed'));

        $service = new ReceiptGenerationService($pdf, $barcodeGenerator);
        $response = $service->generate($transaction);

        $this->assertInstanceOf(Response::class, $response);
    }
}
