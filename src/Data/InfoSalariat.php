<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;

/**
 * Personal data of an employee.
 *
 * Several fields exclude each other in REGES, and a message carrying both
 * sides of a pair is rejected. When both are given, neither is sent:
 *  - nationalitate and apatrid;
 *  - localitate and localitateSalariatStrain.
 * localitateSalariatStrain and detaliiSalariatStrain are only sent for an
 * employee whose nationalitate is not Romania.
 */
final readonly class InfoSalariat
{
    private const ROMANIA = ['ROMÂNIA', 'ROMANIA'];

    /**
     * @param string|null $taraDomiciliu Country name, exactly as in the Nationalitate nomenclator
     * @param string|null $nationalitate Country name, exactly as in the Nationalitate nomenclator
     * @param int|null    $localitate    SIRUTA code of the locality of residence
     */
    public function __construct(
        public ?string $cnp = null,
        public ?string $nume = null,
        public ?string $prenume = null,
        public ?string $adresa = null,
        public ?string $tipActIdentitate = null,
        public ?string $taraDomiciliu = null,
        public ?string $nationalitate = null,
        public ?int $localitate = null,
        public ?\DateTimeInterface $dataNastere = null,
        public ?string $apatrid = null,
        public ?string $localitateSalariatStrain = null,
        public ?string $mentiuni = null,
        public ?bool $radiat = null,
        public ?string $motivRadiere = null,
        public ?string $tipHandicap = null,
        public ?string $gradHandicap = null,
        public ?string $gradInvaliditate = null,
        public ?\DateTimeInterface $dataCertificatHandicap = null,
        public ?string $numarCertificatHandicap = null,
        public ?\DateTimeInterface $dataValabilitateCertificatHandicap = null,
        public ?DetaliiSalariatStrain $detaliiSalariatStrain = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $taraDomiciliu = self::name($this->taraDomiciliu);
        $nationalitate = self::name($this->nationalitate);

        $info = [
            'adresa' => $this->adresa,
            'cnp' => self::normalizeCnp($this->cnp),
            'nume' => $this->nume,
            'prenume' => $this->prenume,
            'tipActIdentitate' => $this->tipActIdentitate,
        ];

        if ($taraDomiciliu) {
            $info['taraDomiciliu'] = [
                'nume' => $taraDomiciliu,
            ];
        }

        if ($nationalitate && empty($this->apatrid)) {
            $info['nationalitate'] = [
                'nume' => $nationalitate,
            ];
        }

        if ($this->localitate && empty($this->localitateSalariatStrain)) {
            $info['localitate'] = [
                'codSiruta' => $this->localitate,
            ];
        }

        if ($this->dataNastere) {
            $info['dataNastere'] = Dates::format($this->dataNastere);
        }

        if ($this->apatrid && !$nationalitate) {
            $info['apatrid'] = $this->apatrid;
        }

        $strain = $this->isStrain();

        if ($this->localitateSalariatStrain && empty($this->localitate) && $strain) {
            $info['localitateSalariatStrain'] = $this->localitateSalariatStrain;
        }

        if ($this->mentiuni) {
            $info['mentiuni'] = $this->mentiuni;
        }

        if ($this->radiat !== null) {
            $info['radiat'] = $this->radiat;
        }

        if ($this->motivRadiere) {
            $info['motivRadiere'] = $this->motivRadiere;
        }

        if ($this->tipHandicap) {
            $info['tipHandicap'] = $this->tipHandicap;
        }

        if ($this->gradHandicap) {
            $info['gradHandicap'] = $this->gradHandicap;
        }

        if ($this->gradInvaliditate) {
            $info['gradInvaliditate'] = $this->gradInvaliditate;
        }

        if ($this->dataCertificatHandicap) {
            $info['dataCertificatHandicap'] = Dates::format($this->dataCertificatHandicap);
        }

        if ($this->numarCertificatHandicap) {
            $info['numarCertificatHandicap'] = $this->numarCertificatHandicap;
        }

        if ($this->dataValabilitateCertificatHandicap) {
            $info['dataValabilitateCertificatHandicap'] = Dates::format($this->dataValabilitateCertificatHandicap);
        }

        if ($strain && $this->detaliiSalariatStrain) {
            $detalii = $this->detaliiSalariatStrain->toArray();
            if ($detalii !== []) {
                $info['detaliiSalariatStrain'] = $detalii;
            }
        }

        return Json::withoutNulls($info);
    }

    /**
     * A foreign citizen: has a nationality other than Romania and is not stateless.
     */
    public function isStrain(): bool
    {
        $nationalitate = self::name($this->nationalitate);

        return $nationalitate !== null
            && !in_array(mb_strtoupper($nationalitate), self::ROMANIA, true)
            && empty($this->apatrid);
    }

    /**
     * Keeps letters and digits only, upper-cased ("180 0612-015459" becomes "1800612015459").
     */
    public static function normalizeCnp(?string $cnp): ?string
    {
        if ($cnp === null) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '', $cnp) ?? '');

        return $normalized !== '' ? $normalized : null;
    }

    private static function name(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
