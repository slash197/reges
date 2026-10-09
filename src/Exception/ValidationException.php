<?php

declare(strict_types=1);

namespace slash197\Reges\Exception;

/**
 * A message that would be rejected by REGES, caught before it is sent.
 * Every problem found is reported at once.
 */
class ValidationException extends RegesException
{
    /**
     * @param array<string, string> $errors Field path (e.g. "continut.cor.cod") to the rule it broke
     */
    public function __construct(public readonly array $errors)
    {
        $details = [];
        foreach ($errors as $field => $rule) {
            $details[] = "{$field} ({$rule})";
        }

        parent::__construct('Invalid REGES message: ' . implode(', ', $details) . '.');
    }

    /**
     * @return list<string>
     */
    public function fields(): array
    {
        return array_keys($this->errors);
    }
}
