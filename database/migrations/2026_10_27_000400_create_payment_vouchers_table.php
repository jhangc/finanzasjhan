<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un pago puede tener varios vouchers. Se migran los que ya existían en voucher_path.
     */
    public function up(): void
    {
        Schema::create('payment_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commitment_payment_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        DB::table('commitment_payments')->whereNotNull('voucher_path')->orderBy('id')
            ->each(function ($payment) {
                DB::table('payment_vouchers')->insert([
                    'user_id' => $payment->user_id,
                    'commitment_payment_id' => $payment->id,
                    'path' => $payment->voucher_path,
                    'original_name' => basename($payment->voucher_path),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('commitment_payments', function (Blueprint $table) {
            $table->dropColumn('voucher_path');
        });
    }

    public function down(): void
    {
        Schema::table('commitment_payments', function (Blueprint $table) {
            $table->string('voucher_path')->nullable()->after('note');
        });

        DB::table('payment_vouchers')->orderBy('id')->each(function ($voucher) {
            DB::table('commitment_payments')->where('id', $voucher->commitment_payment_id)
                ->whereNull('voucher_path')->update(['voucher_path' => $voucher->path]);
        });

        Schema::dropIfExists('payment_vouchers');
    }
};
