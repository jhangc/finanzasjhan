<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuota inicial de una deuda a plazo (ej. departamento).
     */
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('down_payment', 12, 2)->nullable()->after('original_amount');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn('down_payment');
        });
    }
};
