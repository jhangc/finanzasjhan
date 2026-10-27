<?php

namespace App\Services\Parsing;

use App\Models\Envelope;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "25 comida" → monto + sobre.
 */
class QuickEntryParser
{
    private const SIMILARITY_THRESHOLD = 70.0;

    /** Primero el formato con miles (1,250.50), luego el simple (25 / 25.50 / 25,50). */
    private const AMOUNT_PATTERN = '/(?:s\/\.?\s*)?(\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?|\d+(?:[.,]\d{1,2})?)(?![\d])/iu';

    private const CARD_PATTERN = '/\s+(tc|tarjeta)\s*$/iu';

    /**
     * @param  Collection<int, Envelope>  $envelopes
     */
    public function parse(string $input, Collection $envelopes): ParsedEntry
    {
        $text = ' '.trim(preg_replace('/\s+/u', ' ', $input)).' ';

        $amount = null;
        if (preg_match(self::AMOUNT_PATTERN, $text, $match, PREG_OFFSET_CAPTURE)) {
            $amount = $this->normalizeAmount($match[1][0]);
            $text = substr_replace($text, ' ', $match[0][1], strlen($match[0][0]));
        }

        $paidWithCard = false;
        $text = rtrim($text);
        if (preg_match(self::CARD_PATTERN, $text)) {
            $paidWithCard = true;
            $text = preg_replace(self::CARD_PATTERN, '', $text);
        }

        $text = trim(preg_replace('/\s+/u', ' ', $text));

        return new ParsedEntry(
            amount: $amount,
            envelope: $text === '' ? null : $this->matchEnvelope($text, $envelopes),
            text: $text,
            paidWithCard: $paidWithCard,
        );
    }

    /**
     * Match exacto, luego prefijo, luego similar_text > 70 %.
     *
     * @param  Collection<int, Envelope>  $envelopes
     */
    public function matchEnvelope(string $text, Collection $envelopes): ?Envelope
    {
        $needle = self::normalize($text);
        if ($needle === '') {
            return null;
        }

        $candidates = $envelopes->map(fn (Envelope $e) => ['envelope' => $e, 'name' => self::normalize($e->name)]);

        if ($exact = $candidates->firstWhere('name', $needle)) {
            return $exact['envelope'];
        }

        $prefixed = $candidates->filter(fn ($c) => $this->isPrefix($needle, $c['name']));
        if ($prefixed->count() === 1) {
            return $prefixed->first()['envelope'];
        }

        // Varios prefijos posibles: gana el más parecido entre ellos.
        $pool = $prefixed->isNotEmpty() ? $prefixed : $candidates;

        $best = null;
        $bestScore = 0.0;
        foreach ($pool as $candidate) {
            similar_text($needle, $candidate['name'], $score);
            if ($score > $bestScore) {
                [$best, $bestScore] = [$candidate['envelope'], $score];
            }
        }

        return $prefixed->isNotEmpty() || $bestScore > self::SIMILARITY_THRESHOLD ? $best : null;
    }

    /** Minúsculas y sin tildes. */
    public static function normalize(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($value))));
    }

    /** "super" es prefijo de "supermercado"; "fuera" lo es de una palabra de "comida fuera". */
    private function isPrefix(string $needle, string $name): bool
    {
        if (str_starts_with($name, $needle) || str_starts_with($needle, $name.' ')) {
            return true;
        }

        foreach (explode(' ', $name) as $word) {
            if (strlen($needle) >= 3 && str_starts_with($word, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeAmount(string $raw): string
    {
        // 1,250.50 → 1250.50 ; 25,50 → 25.50
        $value = preg_match('/^\d{1,3}(,\d{3})+(\.\d{1,2})?$/', $raw)
            ? str_replace(',', '', $raw)
            : str_replace(',', '.', $raw);

        return Money::of($value);
    }
}
