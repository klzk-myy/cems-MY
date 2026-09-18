<?php

namespace App\Enums;

/**
 * The GL leg a remittance event posts through the inter-branch clearing
 * account (2300).
 *
 * Dispatch moves value OUT of a branch's cash/inventory into clearing;
 * Receipt moves it IN on acknowledgement; Reversal is the same inbound
 * shape as Receipt but describes a cancellation returning the funds to
 * the sender rather than a delivery.
 */
enum RemittanceGlLeg
{
    case Dispatch;
    case Receipt;
    case Reversal;

    /**
     * Whether the leg lands value IN the branch's cash/inventory account
     * (debit side) rather than dispatching it out (credit side).
     */
    public function isInbound(): bool
    {
        return $this !== self::Dispatch;
    }

    /**
     * Past-tense verb used in the cash/inventory journal line narration.
     */
    public function verb(): string
    {
        return match ($this) {
            self::Dispatch => 'dispatched',
            self::Receipt => 'received',
            self::Reversal => 'returned to sender',
        };
    }
}
