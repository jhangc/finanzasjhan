<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuentas de débito (ahorro, sueldo, efectivo) y línea de crédito de las tarjetas.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('bank')->nullable();
            $table->enum('type', ['savings', 'checking', 'cash']);
            $table->char('currency', 3)->default('PEN');
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('color')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('commitments', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->nullable()->after('total_balance');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table) {
            $table->dropColumn('credit_limit');
        });

        Schema::dropIfExists('accounts');
    }
};
