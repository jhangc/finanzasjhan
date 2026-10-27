<?php

namespace App\Services\Budget;

use App\Support\Money;

final readonly class EnvelopeStatus
{
    public const STATE_OK = 'ok';

    public const STATE_WARNING = 'warning';

    public const STATE_OVER = 'over';

    public function __construct(
        public int $id,
        public string $name,
        public ?string $color,
        public string $allocated,
        public string $spent,
        public string $remaining,
        public int $percent,
        public string $state,
    ) {}

    public static function make(int $id, string $name, ?string $color, string $allocated, string $spent): self
    {
        $remaining = Money::sub($allocated, $spent);
        $percent = Money::percent($spent, $allocated);

        $state = match (true) {
            Money::isNegative($remaining) => self::STATE_OVER,
            $percent >= 80 => self::STATE_WARNING,
            default => self::STATE_OK,
        };

        return new self($id, $name, $color, Money::of($allocated), Money::of($spent), $remaining, $percent, $state);
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
