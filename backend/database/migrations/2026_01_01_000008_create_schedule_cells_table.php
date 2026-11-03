<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('schedule_cells', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_pattern_id')->constrained('schedule_patterns')->onDelete('cascade');
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('target_date');
            $table->foreignId('shift_type_id')->nullable()->constrained('shift_types')->onDelete('set null');
            $table->boolean('is_fixed')->default(false);
            $table->timestamps();
            $table->unique(['schedule_pattern_id', 'staff_id', 'target_date'], 'schedule_cell_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('schedule_cells'); }
};
