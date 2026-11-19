<?php

namespace App\Modules\Tags\Contracts;

/** What other modules may show about a tag. */
final readonly class TagSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $color,
    ) {}

    /**
     * @return array{id: string, name: string, color: string|null}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'color' => $this->color];
    }
}
