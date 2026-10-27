<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['envelope', 'commitment_due']);
            $table->foreignId('commitment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_rules');
    }
};
