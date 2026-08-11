<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->string('status', 20)->default('draft');
            $table->string('source', 20);
            $table->string('image_path')->nullable();
            $table->string('note', 500)->nullable();
            $table->decimal('total_calories', 8, 2)->nullable();
            $table->decimal('total_protein', 8, 2)->nullable();
            $table->decimal('total_carbs', 8, 2)->nullable();
            $table->decimal('total_fat', 8, 2)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
