<?php

declare(strict_types=1);

namespace Slash197\Reges\Data;

final readonly class SporSalariu
{
    /**
     * @param string      $nume          Name of the bonus type, as it appears in the nomenclator
     * @param string|null $referinta     Id of the bonus type in the nomenclator
     * @param bool        $sporAngajator True for a bonus type defined by the employer (TipSporAngajator),
     *                                   false for one predefined by REGES (TipSporPredefinit)
     */
    public function __construct(
        public string $nume,
        public int|float|null $valoare,
        public bool $isProcent = false,
        public ?string $referinta = null,
        public bool $sporAngajator = false,
    ) {
    }

    /**
     * @return array<string, mixed>|null Null when the bonus has no name and so cannot be sent
     */
    public function toArray(): ?array
    {
        $nume = trim($this->nume);
        if ($nume === '') {
            return null;
        }

        $tip = [];
        if ($this->sporAngajator) {
            // "$type" has to be the first key: the REGES deserializer answers 400
            // ("metadata property is not the first property") otherwise.
            $tip['$type'] = 'sporAngajator';
        }
        $tip['nume'] = $nume;

        $referinta = trim((string) $this->referinta);
        if ($referinta !== '') {
            $tip['referinta'] = [
                '$type' => 'referinta',
                'id' => $referinta,
            ];
        }

        return [
            'tip' => $tip,
            'valoare' => $this->valoare,
            'isProcent' => $this->isProcent,
        ];
    }

    /**
     * Null leaves the bonuses out of the message (unchanged), an empty list
     * clears them, and a list whose entries are all nameless is left out.
     *
     * @param list<self>|null $sporuri
     *
     * @return list<array<string, mixed>>|null
     */
    public static function listToArray(?array $sporuri): ?array
    {
        if ($sporuri === null) {
            return null;
        }

        if ($sporuri === []) {
            return [];
        }

        $items = [];
        foreach ($sporuri as $spor) {
            $item = $spor->toArray();
            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items ?: null;
    }
}
