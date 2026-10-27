<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Préstamos en cuotas: monto por cuota y periodo (mensual, quincenal, semanal).
     * monthly_amount pasa a ser el equivalente mensual que usa el presupuesto.
     */
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('installment_amount', 12, 2)->nullable()->after('term_months');
            $table->string('frequency', 10)->default('monthly')->after('installment_amount');
        });

        DB::table('commitments')->whereNotNull('term_months')->update([
            'installment_amount' => DB::raw('monthly_amount'),
        ]);
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn(['installment_amount', 'frequency']);
        });
    }
};
