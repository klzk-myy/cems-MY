<?php

namespace App\Enums;

enum DocumentType: string
{
    case MyKad = 'MyKad';
    case Passport = 'Passport';
    case ProofOfAddress = 'Proof_of_Address';
    case Others = 'Others';

    public function label(): string
    {
        return match ($this) {
            self::MyKad => 'MyKad',
            self::Passport => 'Passport',
            self::ProofOfAddress => 'Proof of Address',
            self::Others => 'Others',
        };
    }
}
