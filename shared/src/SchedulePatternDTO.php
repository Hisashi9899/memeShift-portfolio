<?php
namespace Shared\DTO;

class SchedulePatternDTO
{
    public function __construct(
        public readonly int $id,
        public readonly string $month_year,
        public readonly int $pattern_number,
        public readonly bool $is_base,
        public readonly array $cells,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            month_year: $data['month_year'],
            pattern_number: (int)$data['pattern_number'],
            is_base: (bool)($data['is_base'] ?? false),
            cells: array_map(fn($c) => ScheduleCellDTO::fromArray($c), $data['cells'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'month_year' => $this->month_year,
            'pattern_number' => $this->pattern_number,
            'is_base' => $this->is_base,
            'cells' => array_map(fn($c) => $c->toArray(), $this->cells),
        ];
    }
}
