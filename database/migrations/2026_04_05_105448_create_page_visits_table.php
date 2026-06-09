<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_visits', function (Blueprint $table): void {
            $table->id();
            $table->string('page_key');
            $table->string('route_name')->nullable();
            $table->string('visitable_type')->nullable();
            $table->string('visitable_id')->nullable();
            $table->date('date');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['date', 'page_key']);
            $table->index(['visitable_type', 'visitable_id']);
            $table->index(['date', 'views']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_visits');
    }
};
