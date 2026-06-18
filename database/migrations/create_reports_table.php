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
        $table = config('reports.table');
        $tableName = is_string($table) ? $table : 'reports';
        $useUuid = config('reports.morph_key_type') === 'uuid';

        Schema::create($tableName, function (Blueprint $blueprint) use ($useUuid, $tableName): void {
            $blueprint->id();

            // Reporter is nullable so anonymous / guest reports are supported.
            $this->nullableMorph($blueprint, 'reporter', $useUuid);

            // Reported subject.
            $this->nullableMorph($blueprint, 'reported', $useUuid);

            // Resolver (moderator) who closed out the report; nullable for system actions.
            $this->nullableMorph($blueprint, 'resolved_by', $useUuid);

            $blueprint->string('status')->default(Status::Pending->value)->index();
            $blueprint->string('reason')->index();
            $blueprint->text('description')->nullable();
            $blueprint->text('resolution_note')->nullable();

            // Hashed IP / email used to dedupe guest reports.
            $blueprint->string('guest_identifier')->nullable()->index();

            $blueprint->timestamp('resolved_at')->nullable();
            $blueprint->timestamps();
            $blueprint->softDeletes();

            // Aggregation / threshold queries filter by subject + status.
            $blueprint->index(['reported_type', 'reported_id', 'status']);

            // Duplicate detection looks up subject + reporter.
            $blueprint->index(['reported_type', 'reported_id', 'reporter_type', 'reporter_id'], "{$tableName}_dedupe_index");
        });
    }

    private function nullableMorph(Blueprint $blueprint, string $name, bool $useUuid): void
    {
        if ($useUuid) {
            $blueprint->string("{$name}_type")->nullable();
            $blueprint->uuid("{$name}_id")->nullable();
        } else {
            $blueprint->string("{$name}_type")->nullable();
            $blueprint->unsignedBigInteger("{$name}_id")->nullable();
        }

        $blueprint->index(["{$name}_type", "{$name}_id"]);
    }
};
