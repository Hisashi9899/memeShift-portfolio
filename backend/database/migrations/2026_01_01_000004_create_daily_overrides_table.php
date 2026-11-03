<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('daily_overrides', function (Blueprint $table) {
            $table->id();
            $table->date('target_date');
            $table->foreignId('shift_type_id')->constrained('shift_types')->onDelete('cascade');
            $table->integer('count')->default(1);
            $table->timestamps();
            $table->unique(['target_date', 'shift_type_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('daily_overrides'); }
};
