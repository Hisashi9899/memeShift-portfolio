<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('target_date');
            $table->enum('type', ['off', 'want_shift', 'avoid_shift', 'night_only', 'cannot_night', 'fixed_shift']);
            $table->foreignId('shift_type_id')->nullable()->constrained('shift_types')->onDelete('set null');
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['staff_id', 'target_date']);
        });
    }
    public function down(): void { Schema::dropIfExists('preferences'); }
};
