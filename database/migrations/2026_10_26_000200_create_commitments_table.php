<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['debt', 'fixed', 'annual']);
            $table->decimal('total_balance', 12, 2)->nullable();
            $table->decimal('monthly_amount', 12, 2);
            $table->unsignedTinyInteger('pay_day')->nullable();
            $table->unsignedTinyInteger('cut_day')->nullable();
            $table->decimal('interest_rate', 6, 3)->nullable();
            $table->boolean('is_card')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // La tarjeta por defecto vive en users, pero depende de commitments.
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_card_id')->nullable()->after('reminder_email')
                ->constrained('commitments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_card_id');
        });

        Schema::dropIfExists('commitments');
    }
};
