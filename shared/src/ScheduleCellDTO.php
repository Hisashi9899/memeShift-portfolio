<?php
namespace Shared\DTO;

class ScheduleCellDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $schedule_pattern_id,
        public readonly int $staff_id,
        public readonly string $target_date,
        public readonly ?int $shift_type_id,
        public readonly bool $is_fixed,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            schedule_pattern_id: $data['schedule_pattern_id'] ?? null,
            staff_id: (int)$data['staff_id'],
            target_date: $data['target_date'],
            shift_type_id: $data['shift_type_id'] ?? null,
            is_fixed: (bool)($data['is_fixed'] ?? false),
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
