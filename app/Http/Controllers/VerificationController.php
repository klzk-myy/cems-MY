<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Contracts\View\View;

class VerificationController extends Controller
{
    /**
     * Public transaction verification page targeted by receipt QR codes.
     *
     * SECURITY: Only non-sensitive data is returned - status label, transaction
     * date, and the reference. No customer PII, no amounts, no IDs that
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
            'reference' => $transaction?->reference,
        ]);
    }
}
