<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de cuenta de la tarjeta: lo facturado en el último corte (lo que hay que pagar),
     * distinto de la deuda total que incluye los consumos después del corte.
     */
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('statement_balance', 12, 2)->nullable()->after('credit_limit');
            $table->date('statement_date')->nullable()->after('statement_balance');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn(['statement_balance', 'statement_date']);
        });
    }
};
