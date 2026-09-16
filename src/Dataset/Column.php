<?php

declare(strict_types=1);

namespace Bpmore\Furrow\Dataset;

/** What is true of a whole column: its name, what kind of value it holds, and whether it names the rows. */
final class Column
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public function __construct(
        public readonly string $label,
        public readonly string $type = self::TEXT,
        public readonly bool $header = false,
    ) {}

    /** @param  array<string, mixed>  $column */
    public static function fromArray(array $column): self
    {
        $label = $column['label'] ?? '';
        $type = $column['type'] ?? self::TEXT;

        return new self(
            is_scalar($label) ? trim((string) $label) : '',
            $type === self::NUMBER ? self::NUMBER : self::TEXT,
            (bool) ($column['header'] ?? false),
        );
    }

    public function withLabel(string $label): self
    {
        return new self($label, $this->type, $this->header);
    }

    /** @return array{label: string, type: string, header: bool} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'type' => $this->type, 'header' => $this->header];
    }
}
