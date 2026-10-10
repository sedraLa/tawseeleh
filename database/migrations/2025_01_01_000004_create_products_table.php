<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')
                  ->constrained()
                  ->restrictOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            
            $table->index(['restaurant_id', 'is_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
