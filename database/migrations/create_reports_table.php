<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reports\Enums\Status;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->morphs('reporter');
            $table->nullableMorphs('reported');
            $table->string('status')->default(Status::New->value);
            $table->string('type')->default('default');
            $table->text('description');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
