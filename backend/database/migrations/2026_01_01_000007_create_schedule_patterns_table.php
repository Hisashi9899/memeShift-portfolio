<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('schedule_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('month_year', 7);
            $table->tinyInteger('pattern_number')->default(1);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
            $table->unique(['month_year', 'pattern_number']);
        });
    }
    public function down(): void { Schema::dropIfExists('schedule_patterns'); }
};
