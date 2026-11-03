<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('name_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->index();

            // genderize.io
            $table->string('gender', 10)->nullable();
            $table->decimal('gender_probability', 5, 4)->nullable();
            $table->unsignedInteger('gender_count')->default(0);

            // nationalize.io (negara teratas) + first.org (nama & region negara)
            $table->string('country_id', 2)->nullable();
            $table->string('country_name')->nullable();
            $table->string('country_region')->nullable();
            $table->decimal('country_probability', 7, 6)->nullable();
            $table->unsignedInteger('nationality_count')->default(0);

            // Semua kandidat negara dari nationalize.io
            $table->json('countries')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('name_predictions');
    }
};
