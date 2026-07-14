<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_id')->constrained('responses')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('questions')->cascadeOnDelete();
            $table->string('question_key'); // denormalised for stable exports even if question edited
            $table->json('value')->nullable(); // scalar or array, always stored as JSON
            $table->timestamps();

            $table->index(['response_id']);
            $table->index(['question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
