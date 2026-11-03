<?php
namespace Shared\DTO;

class CandidateDTO
{
    public function __construct(
        public readonly string $id,
        public readonly SchedulePatternDTO $schedule,
        public readonly array $features,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            schedule: SchedulePatternDTO::fromArray($data['schedule']),
            features: $data['features'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'schedule' => $this->schedule->toArray(),
            'features' => $this->features,
        ];
    }
}
