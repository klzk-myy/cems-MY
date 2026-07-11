<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Response;
use Picqer\Barcode\BarcodeGeneratorPNG;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ReceiptGenerationService
{
    public function __construct(
        protected PDF $pdf,
        protected BarcodeGeneratorPNG $barcodeGenerator
    ) {}

    public function generate(Transaction $transaction): Response
    {
        $transaction->load(['customer', 'user', 'approver']);

        $barcodeText = str_pad((string) $transaction->id, 10, '0', STR_PAD_LEFT);
        $barcodeImage = $this->generateBarcode($barcodeText);
        $qrCodeImage = $this->generateQrCode($transaction);

        $this->pdf->loadView('transactions.receipt', compact('transaction', 'barcodeImage', 'qrCodeImage', 'barcodeText'));
        $this->pdf->setPaper([0, 0, 226.77, 841.89], 'portrait');

        $filename = 'receipt_'.str_pad((string) $transaction->id, 8, '0', STR_PAD_LEFT).'.pdf';

        return $this->pdf->download($filename);
    }

    private function generateBarcode(string $barcodeText): ?string
    {
        try {
            $data = $this->barcodeGenerator->getBarcode($barcodeText, $this->barcodeGenerator::TYPE_CODE_128);

            return 'data:image/png;base64,'.base64_encode($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function generateQrCode(Transaction $transaction): ?string
    {
        try {
            $data = QrCode::format('png')
                ->size(150)
                ->generate(json_encode([
                    'id' => $transaction->id,
                    'amount' => $transaction->amount_local,
                    'currency' => $transaction->currency_code,
                    'date' => $transaction->created_at->toIso8601String(),
                    'customer_id' => $transaction->customer_id,
                    'type' => $transaction->type->value,
                    'verify' => url('/verify/transaction/'.$transaction->id),
                ]));

            return 'data:image/png;base64,'.base64_encode($data);
        } catch (\Exception $e) {
            return null;
        }
    }
}
