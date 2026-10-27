<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comprobante (foto o PDF) de cada pago. Se guarda en el disco privado.
     */
    public function up(): void
    {
        Schema::table('commitment_payments', function (Blueprint $table) {
            $table->string('voucher_path')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('commitment_payments', function (Blueprint $table) {
            $table->dropColumn('voucher_path');
        });
    }
};
