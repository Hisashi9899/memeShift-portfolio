<?php
namespace Shared\DTO;

class ShiftTypeDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly ?string $start_time,
        public readonly ?string $end_time,
        public readonly string $color,
        public readonly ?int $sort_order,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            start_time: $data['start_time'] ?? null,
            end_time: $data['end_time'] ?? null,
            color: $data['color'] ?? '#1976d2',
            sort_order: $data['sort_order'] ?? null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
