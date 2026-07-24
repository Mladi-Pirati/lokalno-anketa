<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $table->string('code')->nullable()->index(); // GURS OB_ID
            $table->string('name');
            $table->string('slug')->unique();
            $table->longText('svg_path')->nullable();  // projected + simplified SVG path "d"
            $table->json('centroid')->nullable();  // [x, y] in SVG space, for labels
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['region_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipalities');
    }
};
