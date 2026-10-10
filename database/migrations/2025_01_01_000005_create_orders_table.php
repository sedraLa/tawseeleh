<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            
            $table->foreignId('customer_id')
                  ->constrained()
                  ->restrictOnDelete();

            $table->foreignId('restaurant_id')
                  ->constrained()
                  ->restrictOnDelete();

            
            $table->foreignId('driver_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();

            $table->string('status')->default('pending');

           
            $table->string('delivery_address');

            $table->decimal('delivery_fee', 10, 2);

            
            $table->decimal('total_amount', 10, 2);

            $table->timestamps();

        
            $table->index('status');

        
            $table->index(['restaurant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
