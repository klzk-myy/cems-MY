<?php

namespace App\Exceptions\Domain;

class MissingClosingFloatException extends DomainException
{
    /**
     * @param  array<int, string>  $currencyCodes  Open till currencies missing a closing count
     */
    public function __construct(public readonly array $currencyCodes, string $tillId)
    {
        $list = implode(', ', $currencyCodes);

        parent::__construct(
            "Till {$tillId} has open {$list} balance(s) with no closing count. "
            .'Count every currency drawer before closing the session.'
        );
    }
}
