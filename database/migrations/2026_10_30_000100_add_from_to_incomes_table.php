<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De qué cuenta o tarjeta sale el dinero de un ingreso (ej. pasar plata de la tarjeta
     * o de otra cuenta propia): entra en account_id y sale de aquí.
     */
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->foreignId('from_account_id')->nullable()->after('account_id')
                ->constrained('accounts')->nullOnDelete();
            $table->foreignId('from_commitment_id')->nullable()->after('from_account_id')
                ->constrained('commitments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('from_commitment_id');
            $table->dropConstrainedForeignId('from_account_id');
        });
    }
};
