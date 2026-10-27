<?php

namespace App\Services\Budget;

final readonly class CascadeResult
{
    /**
     * @param  list<EnvelopeStatus>  $envelopes
     */
    public function __construct(
        public string $income,
        public bool $incomeIsConfirmed,
        public string $savings,
        public string $commitments,
        public string $paidThisMonth,
        public string $pendingDue,
        public string $availableForEnvelopes,
        public string $allocated,
        public string $overAllocated,
        public string $spent,
        public string $remaining,
        public int $daysLeft,
        public string $todayAllowance,
        public string $requiredIncome,
        public string $incomeGap,
        public array $envelopes,
    ) {}

    public function toArray(): array
    {
        $data = get_object_vars($this);
        $data['envelopes'] = array_map(fn (EnvelopeStatus $e) => $e->toArray(), $this->envelopes);

        return $data;
    }
}
