<?php

namespace App\Services\Parsing;

use App\Support\Money;

/**
 * Lee el texto de una notificación de Yape o Plin.
 */
class YapeNotificationParser
{
    private const PATTERNS = [
        '/yapeaste\s+s\/\.?\s*([\d.,]+)\s+a\s+(.+)/iu',
        '/plin\s*:?\s*enviaste\s+s\/\.?\s*([\d.,]+)\s+a\s+(.+)/iu',
    ];

    /**
     * @return array{amount: string, counterpart: string}|null
     */
    public function parse(string $text): ?array
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, trim($text), $match)) {
                $amount = $this->amount($match[1]);
                $counterpart = trim(preg_replace('/[\s.,;!]+$/u', '', $match[2]));

                if ($amount === null || $counterpart === '') {
                    return null;
                }

                return ['amount' => $amount, 'counterpart' => $counterpart];
            }
        }

        return null;
    }

    private function amount(string $raw): ?string
    {
        $raw = rtrim($raw, '.,');

        // "1,250.00" → miles con coma; "12,50" → decimal con coma.
        $value = preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $raw)
            ? str_replace(',', '', $raw)
            : str_replace(',', '.', $raw);

        if (! is_numeric($value) || Money::cmp($value, '0') <= 0) {
            return null;
        }

        return Money::of($value);
    }
}
