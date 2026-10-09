<?php

declare(strict_types=1);

namespace slash197\Reges\Data;

use slash197\Reges\Support\Dates;
use slash197\Reges\Support\Json;

/**
 * The document a termination, suspension or reactivation is based on.
 */
final readonly class DocumentJustificativ
{
    /**
     * @param string|null $tipDocumentJustificativ Code from the TipDocumentJustificativ nomenclator
     */
    public function __construct(
        public ?string $tipDocumentJustificativ = null,
        public ?string $numarDocumentJustificativ = null,
        public ?\DateTimeInterface $dataDocumentJustificativ = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return Json::withoutNulls([
            'tipDocumentJustificativ' => $this->tipDocumentJustificativ,
            'numarDocumentJustificativ' => $this->numarDocumentJustificativ,
            'dataDocumentJustificativ' => Dates::format($this->dataDocumentJustificativ),
        ]);
    }
}
