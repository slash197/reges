<?php

declare(strict_types=1);

namespace slash197\Reges\Validation;

use slash197\Reges\Exception\ValidationException;
use slash197\Reges\MessageType;
use slash197\Reges\Operation;
use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Uuid;

/**
 * Checks a message body against what its operation needs, so that an
 * incomplete message fails here with every problem listed instead of
 * being rejected by REGES one field at a time.
 *
 * @internal
 */
final class Validator
{
    private const EXTENDED_CONTENT_REQUIRED_FROM = '2025-04-01';

    private const CONTRACT_REFERINTA = [
        Operation::ModificareContract,
        Operation::CorectieContract,
        Operation::RadiereContract,
        Operation::IncetareContract,
        Operation::ReactivareContract,
        Operation::AnulareReactivareContract,
        Operation::CorectieIncetareContract,
        Operation::AnulareIncetareContract,
        Operation::SuspendareContract,
        Operation::ModificareSuspendareContract,
        Operation::IncetareSuspendareContract,
        Operation::CorectieIncetareSuspendareContract,
        Operation::AnulareSuspendareContract,
        Operation::CorectieDetasareContract,
        Operation::PrelungireDetasareContract,
        Operation::ModificareDetasareContract,
        Operation::AnulareDetasareContract,
        Operation::IncetareDetasareContract,
        Operation::AnulareIncetareDetasareContract,
        Operation::CorectieIncetareDetasareContract,
        Operation::AnulareTransferContract,
        Operation::CorectieIstoricContract,
        Operation::RadiereIstoricContract,
        Operation::AdaugareModificareInIstoricContract,
        Operation::AdaugareSuspendareInIstoricContract,
        Operation::CorectieIstoricContractCuPropagare,
        Operation::AdaugareModificareInIstoricContractCuPropagare,
    ];

    private const CONTRACT_CONTINUT = [
        Operation::AdaugareContract,
        Operation::ModificareContract,
        Operation::CorectieContract,
        Operation::CorectieIstoricContract,
        Operation::AdaugareModificareInIstoricContract,
        Operation::AdaugareSuspendareInIstoricContract,
        Operation::CorectieIstoricContractCuPropagare,
        Operation::AdaugareModificareInIstoricContractCuPropagare,
    ];

    private const DOCUMENT_JUSTIFICATIV = [
        Operation::SuspendareContract,
        Operation::ModificareSuspendareContract,
        Operation::IncetareSuspendareContract,
        Operation::CorectieIncetareSuspendareContract,
        Operation::AnulareSuspendareContract,
        Operation::IncetareContract,
        Operation::CorectieIncetareContract,
        Operation::AnulareIncetareContract,
        Operation::ReactivareContract,
        Operation::AnulareReactivareContract,
    ];

    private const MOTIV_RADIERE = [
        Operation::RadiereContract,
        Operation::RadiereIstoricContract,
    ];

    private const ACTIUNE_SUSPENDARE = [
        Operation::SuspendareContract,
        Operation::ModificareSuspendareContract,
        Operation::IncetareSuspendareContract,
        Operation::CorectieIncetareSuspendareContract,
    ];

    private const ACTIUNE_INCETARE = [
        Operation::IncetareContract,
        Operation::CorectieIncetareContract,
    ];

    private const ACTIUNE_REACTIVARE = [
        Operation::ReactivareContract,
    ];

    private const ACTIUNE_DETASARE = [
        Operation::CorectieDetasareContract,
        Operation::PrelungireDetasareContract,
        Operation::ModificareDetasareContract,
        Operation::IncetareDetasareContract,
        Operation::CorectieIncetareDetasareContract,
    ];

    private const ACTIUNE_INCETARE_DETASARE = [
        Operation::IncetareDetasareContract,
        Operation::CorectieIncetareDetasareContract,
    ];

    private const ACTIUNE_INCETARE_SUSPENDARE = [
        Operation::IncetareSuspendareContract,
        Operation::CorectieIncetareSuspendareContract,
    ];

    private const SALARIAT_REFERINTA = [
        Operation::ModificareSalariat,
        Operation::CorectieSalariat,
        Operation::RadiereSalariat,
    ];

    private const MUTARE_REFERINTA = [
        Operation::AcceptarePropunereMutareContract,
        Operation::RespingerePropunereMutareContract,
        Operation::RadierePropunereMutareContract,
    ];

    private const MUTARE_REFERINTA_CONTRACT = [
        Operation::PropunereMutareContract,
        Operation::AcceptarePropunereMutareContract,
    ];

    private const MUTARE_DETALII = [
        Operation::PropunereMutareContract,
    ];

    private const MUTARE_CONTINUT = [
        Operation::PropunereMutareContract,
        Operation::AcceptarePropunereMutareContract,
    ];

    private const DETASARE_REFERINTA = [
        Operation::AcceptarePropunereDetasareContract,
        Operation::RespingerePropunereDetasareContract,
        Operation::RadierePropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
        Operation::IncetarePropunereDetasareContract,
    ];

    private const DETASARE_REFERINTA_CONTRACT = [
        Operation::PropunereDetasareContract,
        Operation::AcceptarePropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
    ];

    private const DETASARE_DETALII = [
        Operation::PropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
    ];

    private const DETASARE_CONTINUT = [
        Operation::PropunereDetasareContract,
        Operation::ModificarePropunereDetasareContract,
        Operation::AcceptarePropunereDetasareContract,
    ];

    public function __construct(private readonly ?NomenclatorLookup $nomenclators = null)
    {
    }

    /**
     * @param array<string, mixed> $body    The message without "$type" and header
     * @param array<string, mixed> $context authorId and sessionId, when set
     *
     * @throws ValidationException
     */
    public function validate(Operation $operation, array $body, array $context = []): void
    {
        $errors = $this->check($context, [
            'authorId' => ['uuid'],
            'sessionId' => ['uuid'],
        ]);

        $rules = match ($operation->messageType()) {
            MessageType::Contract => $this->contractRules($operation, $body),
            MessageType::Salariat => $this->salariatRules($operation),
            MessageType::PropunereMutare => $this->mutareRules($operation, $body),
            MessageType::PropunereDetasare => $this->detasareRules($operation),
        };

        $errors += $this->check($body, $rules);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, list<string>>
     */
    private function contractRules(Operation $operation, array $body): array
    {
        $rules = [];

        if ($operation->in(self::CONTRACT_REFERINTA)) {
            $rules['referintaContract.id'] = ['required', 'uuid'];
        }

        if ($operation->in(self::CONTRACT_CONTINUT)) {
            $rules += $this->continutRules('continut');
            if ($this->requiresExtendedContent($body)) {
                $rules += $this->extendedContinutRules($body['continut'] ?? []);
            }
        }

        if ($operation->in(self::DOCUMENT_JUSTIFICATIV)) {
            $rules += [
                'documentJustificativ' => ['required'],
                'documentJustificativ.tipDocumentJustificativ' => ['required'],
                'documentJustificativ.numarDocumentJustificativ' => ['required'],
                'documentJustificativ.dataDocumentJustificativ' => ['required'],
            ];
        }

        if ($operation->in(self::MOTIV_RADIERE)) {
            $rules['motivRadiere'] = ['required'];
        }

        if (
            $operation->in(self::ACTIUNE_SUSPENDARE)
            || $operation->in(self::ACTIUNE_INCETARE)
            || $operation->in(self::ACTIUNE_REACTIVARE)
            || $operation->in(self::ACTIUNE_DETASARE)
        ) {
            $rules['actiune'] = ['required'];
        }

        if ($operation->in(self::ACTIUNE_SUSPENDARE)) {
            $rules['actiune.dataInceput'] = ['required'];
            $rules['actiune.temeiLegal'] = ['required'];
        }

        if ($operation->in(self::ACTIUNE_INCETARE)) {
            $rules['actiune.dataIncetare'] = ['required'];
            $rules['actiune.temeiLegal'] = ['required'];
        }

        if ($operation->in(self::ACTIUNE_REACTIVARE)) {
            $rules['actiune.dataReactivare'] = ['required'];
            $rules['actiune.temeiLegal'] = ['required'];
        }

        if ($operation->in(self::ACTIUNE_DETASARE)) {
            $rules += [
                'actiune.temeiDetasare' => ['required'],
                'actiune.angajatorCui' => ['required'],
                'actiune.angajatorNume' => ['required'],
                'actiune.cor.cod' => ['required'],
                'actiune.cor.versiune' => ['required'],
                'actiune.dataInceput' => ['required'],
                'actiune.dataSfarsit' => ['required'],
                'actiune.nationalitate.nume' => ['required'],
            ];
        }

        if ($operation->in(self::ACTIUNE_INCETARE_DETASARE)) {
            $rules['actiune.dataIncetareDetasare'] = ['required'];
        }

        if ($operation->in(self::ACTIUNE_INCETARE_SUSPENDARE)) {
            $rules['actiune.dataIncetareSuspendare'] = ['required'];
        }

        return $rules;
    }

    /**
     * @return array<string, list<string>>
     */
    private function salariatRules(Operation $operation): array
    {
        $rules = [
            'info' => ['required'],
            'info.adresa' => ['required', 'max:256'],
            'info.cnp' => ['required', 'max:256'],
            'info.nume' => ['required', 'max:256'],
            'info.prenume' => ['required', 'max:256'],
            'info.taraDomiciliu.nume' => ['required', 'max:256', 'in:' . NomenclatorLookup::NATIONALITATE],
            'info.tipActIdentitate' => ['required', 'max:128', 'in:' . NomenclatorLookup::TIP_ACT_IDENTITATE],
            'info.nationalitate.nume' => ['max:256', 'in:' . NomenclatorLookup::NATIONALITATE],
            'info.localitate.codSiruta' => ['in:' . NomenclatorLookup::LOCALITATE],
        ];

        if ($operation->in(self::SALARIAT_REFERINTA)) {
            $rules['referintaSalariat.id'] = ['required', 'uuid'];
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, list<string>>
     */
    private function mutareRules(Operation $operation, array $body): array
    {
        $rules = [];

        if ($operation->in(self::MUTARE_REFERINTA)) {
            $rules['referinta.id'] = ['required', 'uuid'];
        }

        if ($operation->in(self::MUTARE_REFERINTA_CONTRACT)) {
            $rules['referintaContract.id'] = ['required', 'uuid'];
        }

        if ($operation->in(self::MUTARE_DETALII)) {
            $rules += [
                'tipMutare' => ['required'],
                'tipTransfer' => ($body['tipMutare'] ?? null) === 'Transfer' ? ['required'] : [],
                'cuiAngajatorDestinatie' => ['required'],
                'numeAngajatorDestinatie' => ['required'],
                'nationalitateAngajatorDestinatie.nume' => ['required'],
                'cuiAngajatorSursa' => ['required'],
                'dataPropunere' => ['required'],
                'numarPropunere' => ['required'],
                'dataInceput' => ['required'],
            ];
        }

        if ($operation->in(self::MUTARE_CONTINUT)) {
            $rules += $this->continutRules('continutContract');
            $rules += $this->infoSalariatRules();
        }

        return $rules;
    }

    /**
     * @return array<string, list<string>>
     */
    private function detasareRules(Operation $operation): array
    {
        $rules = [];

        if ($operation->in(self::DETASARE_REFERINTA)) {
            $rules['referinta.id'] = ['required', 'uuid'];
        }

        if ($operation->in(self::DETASARE_REFERINTA_CONTRACT)) {
            $rules['referintaContract.id'] = ['required', 'uuid'];
        }

        if ($operation->in(self::DETASARE_DETALII)) {
            $rules += [
                'temeiDetasare' => ['required'],
                'cuiAngajatorDestinatie' => ['required'],
                'numeAngajatorDestinatie' => ['required'],
                'nationalitateAngajatorDestinatie.nume' => ['required'],
                'cuiAngajatorSursa' => ['required'],
                'dataPropunere' => ['required'],
                'numarPropunere' => ['required'],
                'dataInceput' => ['required'],
                'dataSfarsit' => ['required'],
            ];
        }

        if ($operation->in(self::DETASARE_CONTINUT)) {
            $rules += $this->continutRules('continutContract');
            $rules += $this->infoSalariatRules();
        }

        return $rules;
    }

    /**
     * @return array<string, list<string>>
     */
    private function continutRules(string $prefix): array
    {
        return [
            $prefix => ['required'],
            "{$prefix}.referintaSalariat.id" => ['required', 'uuid'],
            "{$prefix}.cor.cod" => ['required'],
            "{$prefix}.cor.versiune" => ['required'],
            "{$prefix}.dataConsemnare" => ['required'],
            "{$prefix}.dataContract" => ['required'],
            "{$prefix}.dataInceputContract" => ['required'],
            "{$prefix}.numarContract" => ['required'],
            "{$prefix}.radiat" => ['required'],
            "{$prefix}.salariu" => ['required'],
            "{$prefix}.stareCurenta" => ['present'],
            "{$prefix}.timpMunca" => ['required'],
            "{$prefix}.timpMunca.norma" => ['required'],
            "{$prefix}.timpMunca.repartizare" => ['required'],
            "{$prefix}.tipContract" => ['required'],
            "{$prefix}.tipDurata" => ['required'],
            "{$prefix}.tipNorma" => ['required'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function infoSalariatRules(): array
    {
        return [
            'infoSalariat' => ['required'],
            'infoSalariat.adresa' => ['required'],
            'infoSalariat.cnp' => ['required'],
            'infoSalariat.nume' => ['required'],
            'infoSalariat.prenume' => ['required'],
            'infoSalariat.taraDomiciliu.nume' => ['required'],
            'infoSalariat.tipActIdentitate' => ['required'],
        ];
    }

    /**
     * What a contract additionally has to say when it is recorded from 1 April 2025.
     *
     * @param array<mixed> $continut
     *
     * @return array<string, list<string>>
     */
    private function extendedContinutRules(array $continut): array
    {
        $repartizareMunca = $continut['timpMunca']['repartizareMunca'] ?? null;

        return [
            'continut.timpMunca.intervalTimp' => ['required'],
            'continut.timpMunca.repartizareMunca' => ['required'],
            'continut.timpMunca.tipTura' => $repartizareMunca === 'Schimburi' ? ['required'] : [],
            'continut.timpMunca.inceputInterval' => $repartizareMunca === 'Zilnic' ? ['required'] : [],
            'continut.timpMunca.sfarsitInterval' => $repartizareMunca === 'Zilnic' ? ['required'] : [],
            'continut.tipLocMunca' => ['required'],
            'continut.judetLocMunca' => ['required'],
            'continut.localitateLocMunca.codSiruta' => ($continut['tipLocMunca'] ?? null) === 'Fix' ? ['required'] : [],
            'continut.nivelStudii' => ['required'],
        ];
    }

    /**
     * REGES asks for more detail on any contract recorded from 1 April 2025.
     *
     * @param array<string, mixed> $body
     */
    private function requiresExtendedContent(array $body): bool
    {
        $date = $body['continut']['dataConsemnare'] ?? null;
        if (!is_string($date) || $date === '') {
            return false;
        }

        try {
            $timezone = new \DateTimeZone(Dates::TIMEZONE);
            $day = (new \DateTimeImmutable($date))->setTimezone($timezone)->format('Y-m-d');

            return $day >= self::EXTENDED_CONTENT_REQUIRED_FROM;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @param array<string, mixed>        $data
     * @param array<string, list<string>> $rules
     *
     * @return array<string, string>
     */
    private function check(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            [$exists, $value] = $this->get($data, $field);

            foreach ($fieldRules as $rule) {
                if (!$this->passes($rule, $exists, $value)) {
                    $errors[$field] = $rule;
                    break;
                }
            }
        }

        return $errors;
    }

    private function passes(string $rule, bool $exists, mixed $value): bool
    {
        if ($rule === 'present') {
            return $exists;
        }

        if ($rule === 'required') {
            return !$this->isBlank($value);
        }

        // Everything below only applies to a value that is there.
        if ($this->isBlank($value)) {
            return true;
        }

        if ($rule === 'uuid') {
            return Uuid::isValid($value);
        }

        if (str_starts_with($rule, 'max:')) {
            return !is_string($value) || mb_strlen($value) <= (int) substr($rule, 4);
        }

        if (str_starts_with($rule, 'in:')) {
            return $this->nomenclators === null
                || !is_scalar($value)
                || $this->nomenclators->has(substr($rule, 3), (string) $value);
        }

        throw new \LogicException("Unknown validation rule: {$rule}");
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null
            || (is_string($value) && trim($value) === '')
            || $value === [];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{bool, mixed} Whether the dotted path exists, and its value
     */
    private function get(array $data, string $path): array
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }

        return [true, $value];
    }
}
