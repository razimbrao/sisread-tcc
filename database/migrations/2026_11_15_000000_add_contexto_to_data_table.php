<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->json('contexto')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropColumn('contexto');
        });
    }
};
