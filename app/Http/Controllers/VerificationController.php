<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class VerificationController extends Controller
{
    /**
     * Public transaction verification page targeted by receipt QR codes.
     *
     * SECURITY: Only non-sensitive data is returned - status label, transaction
     * date, and a masked reference. No customer PII, no amounts, no IDs that
     * could enumerate other records. Unknown or malformed references render the
     * same generic "not verifiable" response with HTTP 200 so attackers cannot
     * use status codes to probe valid references.
     */
    public function show(string $reference): View
    {
        $transaction = null;

        if (preg_match('/^TX-(\d{1,})$/i', trim($reference), $matches)) {
            $transaction = Transaction::find((int) $matches[1]);
        }

        return view('verification.transaction', [
            'verified' => $transaction !== null,
            'status' => $transaction?->status?->label(),
            'date' => $transaction?->created_at?->format('Y-m-d'),
            'masked_reference' => $transaction ? $this->maskReference($transaction->reference) : null,
        ]);
    }

    private function maskReference(string $reference): string
    {
        return Str::upper(Str::substr($reference, 0, 5).'****'.Str::substr($reference, -2));
    }
}
