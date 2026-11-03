<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('employment_type', ['fulltime', 'parttime'])->default('fulltime');
            $table->boolean('can_night_shift')->default(true);
            $table->integer('max_consecutive_days')->default(5);
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('staff'); }
};
