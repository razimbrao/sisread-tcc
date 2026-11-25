<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data', function (Blueprint $table) {
            $table->id();
            $table->json('data')->nullable();
            $table->boolean('finished')->default(false);
            $table->timestamp('searched_at');
            $table->double('time')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data');
    }
};
