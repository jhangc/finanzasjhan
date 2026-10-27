<?php

namespace App\Services\Movements;

use App\Models\Account;
use App\Models\Commitment;
use App\Models\Envelope;
use App\Models\Movement;
use App\Models\Period;
use App\Models\User;
use App\Services\Parsing\QuickEntryParser;
use App\Services\Parsing\YapeNotificationParser;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Convierte el texto del input rápido en un gasto, o pide elegir la categoría.
 *
 * De dónde sale el dinero, en este orden:
 *   1. la última palabra del texto: "tc"/"tarjeta" (tarjeta por defecto), o el nombre/banco
 *      de una cuenta o tarjeta ("25 comida efectivo", "40 gasolina bcp");
 *   2. lo elegido en "Pagas con" (source = "account:ID" | "card:ID" | "none");
 *   3. la cuenta por defecto del usuario.
 */
class QuickEntryService
{
    public const CREATED = 'created';

    public const CHOOSE = 'choose';

    public const INVALID = 'invalid';

    public function __construct(
        private QuickEntryParser $parser,
        private YapeNotificationParser $yape,
    ) {}

    /**
     * @param  array{input: string, envelope_id?: ?int, amount?: ?string, paid_with_card?: ?bool, note?: ?string, source?: ?string}  $data
     */
    public function handle(User $user, Period $period, array $data): array
    {
        $envelopes = $period->envelopes()->get();
        $accounts = Account::ownedBy($user)->orderBy('sort_order')->get();
        $cards = Commitment::ownedBy($user)->where('is_card', true)->where('active', true)->get();

        // Segunda vuelta: viene la categoría elegida con los botones.
        if (! empty($data['envelope_id']) && ! empty($data['amount'])) {
            $envelope = $envelopes->firstWhere('id', (int) $data['envelope_id']);

            if ($envelope === null) {
                return ['status' => self::INVALID, 'message' => 'Esa categoría no es de este mes.'];
            }

            [$account, $card] = $this->resolveSource($user, $data['source'] ?? null, (bool) ($data['paid_with_card'] ?? false), $accounts, $cards);

            return $this->create($user, $envelope, Money::of($data['amount']), $account, $card, $data['note'] ?? null, (bool) ($data['paid_with_card'] ?? false));
        }

        // ¿La última palabra dice de dónde sale? ("efectivo", "bcp", "visa")
        [$input, $textSource] = $this->sourceFromText($data['input'], $accounts, $cards);

        [$amount, $envelope, $paidWithCard, $note] = $this->parse($input, $envelopes);

        if ($amount === null || Money::cmp($amount, '0') <= 0) {
            return ['status' => self::INVALID, 'message' => 'Escribe un monto, por ejemplo: 25 comida'];
        }

        if (Money::cmp($amount, '999999.99') > 0) {
            return ['status' => self::INVALID, 'message' => 'El monto es demasiado grande.'];
        }

        $source = $textSource ?? ($data['source'] ?? null);

        if ($envelope === null) {
            return [
                'status' => self::CHOOSE,
                'amount' => $amount,
                'paid_with_card' => $paidWithCard,
                'source' => $source,
                'note' => $note,
                'envelopes' => $envelopes->map(fn (Envelope $e) => ['id' => $e->id, 'name' => $e->name, 'color' => $e->color])->values()->all(),
            ];
        }

        [$account, $card] = $this->resolveSource($user, $source, $paidWithCard, $accounts, $cards);

        return $this->create($user, $envelope, $amount, $account, $card, $note, $paidWithCard);
    }

    /**
     * Si la última palabra coincide con una cuenta o tarjeta, la quita del texto.
     *
     * @return array{0: string, 1: ?string}
     */
    private function sourceFromText(string $input, Collection $accounts, Collection $cards): array
    {
        $words = preg_split('/\s+/u', trim($input));
        if (count($words) < 2) {
            return [$input, null];
        }

        $last = QuickEntryParser::normalize(end($words));
        if (mb_strlen($last) < 3 || is_numeric(str_replace(',', '.', $last))) {
            return [$input, null];
        }

        $matches = fn (?string $text) => $text !== null && collect(explode(' ', QuickEntryParser::normalize($text)))
            ->contains(fn ($word) => $word !== '' && str_starts_with($word, $last));

        $account = $accounts->first(fn (Account $a) => $matches($a->name) || $matches($a->bank)
            || ($a->type === 'cash' && in_array($last, ['efectivo', 'cash'], true)));
        $card = $account ? null : $cards->first(fn (Commitment $c) => $matches($c->name));

        if (! $account && ! $card) {
            return [$input, null];
        }

        array_pop($words);

        return [implode(' ', $words), $account ? "account:{$account->id}" : "card:{$card->id}"];
    }

    /**
     * @return array{0: ?Account, 1: ?Commitment}
     */
    private function resolveSource(User $user, ?string $source, bool $paidWithCard, Collection $accounts, Collection $cards): array
    {
        if ($paidWithCard) {
            return [null, $this->defaultCard($user, $cards)];
        }

        if ($source === 'none') {
            return [null, null];
        }

        if ($source && preg_match('/^(account|card):(\d+)$/', $source, $m)) {
            return $m[1] === 'account'
                ? [$accounts->firstWhere('id', (int) $m[2]), null]
                : [null, $cards->firstWhere('id', (int) $m[2])];
        }

        return [$accounts->firstWhere('id', $user->default_account_id), null];
    }

    /**
     * @return array{0: ?string, 1: ?Envelope, 2: bool, 3: ?string}
     */
    private function parse(string $input, $envelopes): array
    {
        // Notificación de Yape/Plin pegada en el input.
        if ($yape = $this->yape->parse($input)) {
            return [$yape['amount'], $this->parser->matchEnvelope($yape['counterpart'], $envelopes), false, $yape['counterpart']];
        }

        $entry = $this->parser->parse($input, $envelopes);

        // Si no hubo match, el texto queda como nota.
        $note = $entry->envelope === null && $entry->text !== '' ? $entry->text : null;

        return [$entry->amount, $entry->envelope, $entry->paidWithCard, $note];
    }

    private function create(User $user, Envelope $envelope, string $amount, ?Account $account, ?Commitment $card, ?string $note, bool $askedForCard): array
    {
        $movement = Movement::create([
            'user_id' => $user->id,
            'envelope_id' => $envelope->id,
            'amount' => $amount,
            'spent_at' => now(),
            'note' => $note,
            'account_id' => $account?->id,
            'paid_with_commitment_id' => $card?->id,
        ]);

        $message = 'Registrado '.Money::format($amount).' en '.$envelope->name;
        if ($account) {
            $message .= ' desde '.$account->name;
        } elseif ($card) {
            $message .= ' con '.$card->name;
        } elseif ($askedForCard) {
            $message .= '. No tienes tarjeta por defecto: se registró sin tarjeta';
        }

        return ['status' => self::CREATED, 'message' => $message, 'movement' => $movement->load('envelope')];
    }

    /** La tarjeta por defecto del usuario, o su única tarjeta activa. */
    private function defaultCard(User $user, Collection $cards): ?Commitment
    {
        $default = $cards->firstWhere('id', $user->default_card_id);

        return $default ?? ($cards->count() === 1 ? $cards->first() : null);
    }
}
