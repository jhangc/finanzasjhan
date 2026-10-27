<?php

namespace App\Http\Requests;

use App\Models\Account;
use App\Models\Commitment;
use App\Models\Envelope;
use App\Services\Debts\VoucherStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Pantalla "Anotar": un gasto (qué pagas + con qué pagas) o un ingreso (a qué cuenta entra).
 *
 * target: "envelope:ID" (categoría) | "commitment:ID" (gasto fijo, préstamo o tarjeta)
 * source: "account:ID" | "card:ID" | "none"
 */
class RegisterTransactionRequest extends BaseRequest
{
    public ?Envelope $envelope = null;

    public ?Commitment $commitment = null;

    public ?Account $account = null;

    public ?Commitment $card = null;

    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:expense,income'],
            'amount' => $this->money(),
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'target' => ['required_if:kind,expense', 'nullable', 'regex:/^(envelope|commitment):\d+$/'],
            'source' => ['required_if:kind,expense', 'nullable', 'regex:/^(account:\d+|card:\d+|none)$/'],
            'income_source' => ['required_if:kind,income', 'nullable', 'string', 'max:100'],
            'income_account' => ['nullable', 'integer', $this->owned('accounts')],
            'vouchers' => ['nullable', 'array', 'max:'.VoucherStorage::MAX_PER_PAYMENT],
            'vouchers.*' => VoucherStorage::FILE_RULES,
        ];
    }

    public function attributes(): array
    {
        return [
            'target' => 'qué estás pagando',
            'source' => 'con qué pagas',
            'income_source' => 'de dónde viene',
            'income_account' => 'cuenta',
            'date' => 'fecha',
        ] + parent::attributes();
    }

    public function messages(): array
    {
        return [
            'target.required_if' => 'Elige qué estás pagando.',
            'source.required_if' => 'Elige con qué pagas.',
            'income_source.required_if' => 'Escribe de dónde viene el ingreso.',
        ];
    }

    /** @return array<UploadedFile> */
    public function voucherFiles(): array
    {
        return array_values(array_filter((array) $this->file('vouchers', [])));
    }

    public function after(): array
    {
        return [fn (Validator $validator) => $this->resolveSelections($validator)];
    }

    /** Convierte "envelope:5" / "account:2" en modelos del usuario y valida la combinación. */
    private function resolveSelections(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty() || $this->input('kind') !== 'expense') {
            return;
        }

        $user = $this->user();
        [$targetType, $targetId] = explode(':', $this->input('target'));

        if ($targetType === 'envelope') {
            $this->envelope = Envelope::ownedBy($user)->with('period')->find($targetId);
            if (! $this->envelope) {
                $validator->errors()->add('target', 'Esa categoría no existe.');

                return;
            }

            $date = CarbonImmutable::parse($this->input('date'));
            if ($this->envelope->period->year !== $date->year || $this->envelope->period->month !== $date->month) {
                $validator->errors()->add('date', 'La categoría «'.$this->envelope->name.'» es de otro mes: usa una fecha de ese mes.');
            }
        } else {
            $this->commitment = Commitment::ownedBy($user)->find($targetId);
            if (! $this->commitment) {
                $validator->errors()->add('target', 'Ese gasto fijo no existe.');

                return;
            }
        }

        $source = $this->input('source');
        if ($source === 'none') {
            return;
        }

        [$sourceType, $sourceId] = explode(':', $source);

        if ($sourceType === 'account') {
            $this->account = Account::ownedBy($user)->find($sourceId);
            if (! $this->account) {
                $validator->errors()->add('source', 'Esa cuenta no existe.');
            }

            return;
        }

        $this->card = Commitment::ownedBy($user)->where('is_card', true)->find($sourceId);
        if (! $this->card) {
            $validator->errors()->add('source', 'Esa tarjeta no existe.');
        } elseif ($this->commitment?->is_card) {
            $validator->errors()->add('source', 'Una tarjeta se paga desde una cuenta, no con otra tarjeta.');
        }
    }
}
