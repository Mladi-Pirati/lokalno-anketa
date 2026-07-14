<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->cascadeOnDelete();
            $table->string('key'); // machine key, unique per survey
            $table->string('type'); // text, textarea, radio, checkbox, select, scale, number, email, tel, date, boolean, section
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->text('placeholder')->nullable();
            $table->json('options')->nullable(); // [{value,label}] for choice types
            $table->json('config')->nullable();   // type-specific: {min,max,step,rows,max_select,...}
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['survey_id', 'key']);
            $table->index(['survey_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
