<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommitmentPaymentRequest;
use App\Http\Requests\UpdateCommitmentPaymentRequest;
use App\Models\CommitmentPayment;
use App\Models\PaymentVoucher;
use App\Services\Budget\PeriodBuilder;
use App\Services\Debts\VoucherStorage;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CommitmentPaymentController extends Controller
{
    public function store(StoreCommitmentPaymentRequest $request, PeriodBuilder $periods, VoucherStorage $vouchers): RedirectResponse
    {
        $this->authorize('create', CommitmentPayment::class);

        $data = $request->paymentData();
        $paidAt = CarbonImmutable::parse($data['paid_at']);
        $data['period_id'] = $periods->generate($request->user(), $paidAt->year, $paidAt->month)->id;

        DB::transaction(function () use ($request, $data, $vouchers) {
            $payment = $request->user()->commitmentPayments()->create($data);
            $vouchers->add($payment, $request->voucherFiles());
        });

        return back()->with('status', 'Pago registrado.');
    }

    public function update(UpdateCommitmentPaymentRequest $request, CommitmentPayment $commitmentPayment, PeriodBuilder $periods, VoucherStorage $vouchers): RedirectResponse
    {
        $this->authorize('update', $commitmentPayment);

        $data = $request->paymentData();
        $paidAt = CarbonImmutable::parse($data['paid_at']);
        $data['period_id'] = $periods->generate($request->user(), $paidAt->year, $paidAt->month)->id;

        $removeIds = array_map('intval', $request->validated('remove_vouchers') ?? []);
        $kept = $commitmentPayment->vouchers()->whereNotIn('id', $removeIds)->count();
        if ($kept + count($request->voucherFiles()) > VoucherStorage::MAX_PER_PAYMENT) {
            return back()->withErrors(['vouchers' => 'Puedes tener hasta '.VoucherStorage::MAX_PER_PAYMENT.' vouchers por pago.']);
        }

        DB::transaction(function () use ($request, $commitmentPayment, $data, $vouchers, $removeIds) {
            $commitmentPayment->update($data);
            $vouchers->remove($commitmentPayment, $removeIds);
            $vouchers->add($commitmentPayment, $request->voucherFiles());
        });

        return back()->with('status', 'Pago actualizado.');
    }

    public function destroy(CommitmentPayment $commitmentPayment): RedirectResponse
    {
        $this->authorize('delete', $commitmentPayment);

        $commitmentPayment->delete();

        return back()->with('status', 'Pago eliminado.');
    }

    /** Muestra un voucher solo a su dueño. */
    public function voucher(PaymentVoucher $voucher, VoucherStorage $vouchers): BinaryFileResponse
    {
        $this->authorize('view', $voucher);

        abort_unless($vouchers->exists($voucher), 404);

        return response()->file($vouchers->absolutePath($voucher), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
