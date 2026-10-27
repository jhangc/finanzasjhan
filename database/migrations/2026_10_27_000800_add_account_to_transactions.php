<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De qué cuenta sale (o a cuál entra) el dinero de cada gasto, pago e ingreso.
     * Los pagos de gastos fijos también pueden hacerse con tarjeta de crédito.
     */
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('paid_with_commitment_id')
                ->constrained('accounts')->nullOnDelete();
        });

        Schema::table('commitment_payments', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('note')
                ->constrained('accounts')->nullOnDelete();
            $table->foreignId('paid_with_commitment_id')->nullable()->after('account_id')
                ->constrained('commitments')->nullOnDelete();
        });

        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('confirmed')
                ->constrained('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', fn (Blueprint $table) => $table->dropConstrainedForeignId('account_id'));
        Schema::table('commitment_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_with_commitment_id');
            $table->dropConstrainedForeignId('account_id');
        });
        Schema::table('movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('account_id'));
    }
};
