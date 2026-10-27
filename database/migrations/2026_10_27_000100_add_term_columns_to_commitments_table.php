<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deudas a plazo (departamento, préstamo): monto original, número de cuotas y primer pago.
     */
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('original_amount', 12, 2)->nullable()->after('total_balance');
            $table->unsignedSmallInteger('term_months')->nullable()->after('original_amount');
            $table->date('start_date')->nullable()->after('term_months');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn(['original_amount', 'term_months', 'start_date']);
        });
    }
};
