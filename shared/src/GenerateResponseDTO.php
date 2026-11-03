<?php
namespace Shared\DTO;

class GenerateResponseDTO
{
    public function __construct(
        public readonly array $candidates,
        public readonly array $patterns,
        public readonly array $conflicts,
        public readonly array $relaxed_constraints,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            candidates: array_map(fn($c) => CandidateDTO::fromArray($c), $data['candidates'] ?? []),
            patterns: array_map(fn($p) => SchedulePatternDTO::fromArray($p), $data['patterns'] ?? []),
            conflicts: $data['conflicts'] ?? [],
            relaxed_constraints: $data['relaxed_constraints'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'candidates' => array_map(
                fn($c) => $c instanceof CandidateDTO ? $c->toArray() : CandidateDTO::fromArray($c)->toArray(),
                $this->candidates
            ),
            'patterns' => array_map(
                fn($p) => $p instanceof SchedulePatternDTO ? $p->toArray() : SchedulePatternDTO::fromArray($p)->toArray(),
                $this->patterns
            ),
            'conflicts' => $this->conflicts,
            'relaxed_constraints' => $this->relaxed_constraints,
        ];
    }
}
