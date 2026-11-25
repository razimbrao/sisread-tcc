<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->unique('searched_at');
        });
    }

    public function down(): void
    {
        Schema::table('data', function (Blueprint $table) {
            $table->dropUnique(['searched_at']);
        });
    }
};
