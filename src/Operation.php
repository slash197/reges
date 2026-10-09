<?php

declare(strict_types=1);

namespace Slash197\Reges;

enum Operation: string
{
    case AdaugareContract = 'AdaugareContract';
    case ModificareContract = 'ModificareContract';
    case CorectieContract = 'CorectieContract';
    case RadiereContract = 'RadiereContract';
    case IncetareContract = 'IncetareContract';
    case ReactivareContract = 'ReactivareContract';
    case AnulareReactivareContract = 'AnulareReactivareContract';
    case CorectieIncetareContract = 'CorectieIncetareContract';
    case AnulareIncetareContract = 'AnulareIncetareContract';
    case SuspendareContract = 'SuspendareContract';
    case ModificareSuspendareContract = 'ModificareSuspendareContract';
    case IncetareSuspendareContract = 'IncetareSuspendareContract';
    case CorectieIncetareSuspendareContract = 'CorectieIncetareSuspendareContract';
    case AnulareSuspendareContract = 'AnulareSuspendareContract';
    case CorectieDetasareContract = 'CorectieDetasareContract';
    case PrelungireDetasareContract = 'PrelungireDetasareContract';
    case ModificareDetasareContract = 'ModificareDetasareContract';
    case AnulareDetasareContract = 'AnulareDetasareContract';
    case IncetareDetasareContract = 'IncetareDetasareContract';
    case AnulareIncetareDetasareContract = 'AnulareIncetareDetasareContract';
    case CorectieIncetareDetasareContract = 'CorectieIncetareDetasareContract';
    case AnulareTransferContract = 'AnulareTransferContract';
    case PropunereDetasareContract = 'PropunereDetasareContract';
    case AcceptarePropunereDetasareContract = 'AcceptarePropunereDetasareContract';
    case RespingerePropunereDetasareContract = 'RespingerePropunereDetasareContract';
    case RadierePropunereDetasareContract = 'RadierePropunereDetasareContract';
    case ModificarePropunereDetasareContract = 'ModificarePropunereDetasareContract';
    case IncetarePropunereDetasareContract = 'IncetarePropunereDetasareContract';
    case PropunereMutareContract = 'PropunereMutareContract';
    case AcceptarePropunereMutareContract = 'AcceptarePropunereMutareContract';
    case RespingerePropunereMutareContract = 'RespingerePropunereMutareContract';
    case RadierePropunereMutareContract = 'RadierePropunereMutareContract';
    case CorectieIstoricContract = 'CorectieIstoricContract';
    case RadiereIstoricContract = 'RadiereIstoricContract';
    case AdaugareModificareInIstoricContract = 'AdaugareModificareInIstoricContract';
    case AdaugareSuspendareInIstoricContract = 'AdaugareSuspendareInIstoricContract';
    case CorectieIstoricContractCuPropagare = 'CorectieIstoricContractCuPropagare';
    case AdaugareModificareInIstoricContractCuPropagare = 'AdaugareModificareInIstoricContractCuPropagare';
    case InregistrareSalariat = 'InregistrareSalariat';
    case ModificareSalariat = 'ModificareSalariat';
    case CorectieSalariat = 'CorectieSalariat';
    case RadiereSalariat = 'RadiereSalariat';

    public function messageType(): MessageType
    {
        return match ($this) {
            self::InregistrareSalariat,
            self::ModificareSalariat,
            self::CorectieSalariat,
            self::RadiereSalariat => MessageType::Salariat,

            self::PropunereDetasareContract,
            self::AcceptarePropunereDetasareContract,
            self::RespingerePropunereDetasareContract,
            self::RadierePropunereDetasareContract,
            self::ModificarePropunereDetasareContract,
            self::IncetarePropunereDetasareContract => MessageType::PropunereDetasare,

            self::PropunereMutareContract,
            self::AcceptarePropunereMutareContract,
            self::RespingerePropunereMutareContract,
            self::RadierePropunereMutareContract => MessageType::PropunereMutare,

            default => MessageType::Contract,
        };
    }

    /**
     * @param list<self> $operations
     */
    public function in(array $operations): bool
    {
        return in_array($this, $operations, true);
    }
}
