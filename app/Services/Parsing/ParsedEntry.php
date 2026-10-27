<?php

namespace App\Services\Parsing;

use App\Models\Envelope;

final readonly class ParsedEntry
{
    public function __construct(
        public ?string $amount,
        public ?Envelope $envelope,
        public string $text,
        public bool $paidWithCard = false,
        public ?string $note = null,
    ) {}

    public function hasAmount(): bool
    {
        return $this->amount !== null;
    }

    public function isComplete(): bool
    {
        return $this->amount !== null && $this->envelope !== null;
    }
}
