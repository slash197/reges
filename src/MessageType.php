<?php

declare(strict_types=1);

namespace Slash197\Reges;

/**
 * The polymorphic "$type" of a message, which also decides the endpoint it is posted to.
 */
enum MessageType: string
{
    case Contract = 'contract';
    case Salariat = 'salariat';
    case PropunereMutare = 'propuneremutarecontract';
    case PropunereDetasare = 'propuneredetasarecontract';

    /**
     * Proposal messages have their own endpoints. Posting them to /api/Contract
     * intermittently answers with a bare 500.
     */
    public function path(): string
    {
        return match ($this) {
            self::Contract => '/api/Contract',
            self::Salariat => '/api/Salariat',
            self::PropunereMutare => '/api/Mutare/Propunere',
            self::PropunereDetasare => '/api/Detasare/Propunere',
        };
    }
}
