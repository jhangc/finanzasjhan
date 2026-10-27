<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Categorías de ingreso que el usuario ve como atajos al anotar ("Sueldo", "Apuestas"…).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('income_sources')->nullable()->after('default_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('income_sources');
        });
    }
};
