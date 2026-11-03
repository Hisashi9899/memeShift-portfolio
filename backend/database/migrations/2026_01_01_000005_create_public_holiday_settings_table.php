<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('public_holiday_settings', function (Blueprint $table) {
            $table->id();
            $table->string('month_year', 7)->unique();
            $table->integer('count')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('public_holiday_settings'); }
};
