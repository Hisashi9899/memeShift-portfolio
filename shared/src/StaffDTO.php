<?php
namespace Shared\DTO;

class StaffDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $name,
        public readonly string $employment_type,
        public readonly bool $can_night_shift,
        public readonly int $max_consecutive_days,
        public readonly ?string $start_date,
        public readonly ?string $end_date,
        public readonly bool $active,
        public readonly ?int $sort_order,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            employment_type: $data['employment_type'] ?? 'fulltime',
            can_night_shift: (bool)($data['can_night_shift'] ?? true),
            max_consecutive_days: (int)($data['max_consecutive_days'] ?? 5),
            start_date: $data['start_date'] ?? null,
            end_date: $data['end_date'] ?? null,
            active: (bool)($data['active'] ?? true),
            sort_order: $data['sort_order'] ?? null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
