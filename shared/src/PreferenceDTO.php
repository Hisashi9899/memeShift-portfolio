<?php
namespace Shared\DTO;

class PreferenceDTO
{
    public function __construct(
        public readonly ?int $id,
        public readonly ?int $staff_id,
        public readonly string $target_date,
        public readonly string $type,
        public readonly ?int $shift_type_id,
        public readonly ?string $priority,
        public readonly ?string $note,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            staff_id: $data['staff_id'] ?? null,
            target_date: $data['target_date'],
            type: $data['type'],
            shift_type_id: $data['shift_type_id'] ?? null,
            priority: $data['priority'] ?? null,
            note: $data['note'] ?? null,
        );
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
