<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("skill_types", function (Blueprint $table) {
            $table->id();
            $table->string("name", 50);
            $table->string("category", 50)->nullable();
            $table->integer("sort_order")->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("skill_types");
    }
};
