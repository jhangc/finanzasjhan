<?php

namespace App\Services\Debts;

use Carbon\CarbonImmutable;

final readonly class CardCycle
{
    public function __construct(
        public CarbonImmutable $currentStart,
        public CarbonImmutable $currentEnd,
        public string $currentSpent,
        public CarbonImmutable $closedStart,
        public CarbonImmutable $closedEnd,
        public string $amountToAvoidInterest,
        public CarbonImmutable $paymentDueDate,
    ) {}
}
