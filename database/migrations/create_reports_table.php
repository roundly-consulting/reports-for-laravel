<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;
use RoundlyConsulting\Reports\Enums\Status;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('reports.table');
        $tableName = is_string($table) ? $table : 'reports';

        // Throws for an unrecognized value, so a typo in the host's config fails
        // the migration instead of quietly building bigint columns.
        $keyType = KeyType::fromConfig('reports.key_type');

        Schema::create($tableName, function (Blueprint $blueprint) use ($keyType, $tableName): void {
            $blueprint->id();

            // Reporter is nullable so anonymous / guest reports are supported.
            $blueprint->morphKey('reporter', $keyType, nullable: true);

            // Reported subject.
            $blueprint->polymorphicSubject('reported', $keyType, nullable: true);

            // Resolver (moderator) who closed out the report; nullable for system actions.
            $blueprint->morphKey('resolved_by', $keyType, nullable: true);

            $blueprint->string('status')->default(Status::Pending->value)->index();
            $blueprint->string('reason')->index();
            $blueprint->text('description')->nullable();
            $blueprint->text('resolution_note')->nullable();

            // Hashed IP / email used to dedupe guest reports.
            $blueprint->string('guest_identifier')->nullable()->index();

            $blueprint->timestamp('resolved_at')->nullable();
            $blueprint->auditable();

            // Aggregation / threshold queries filter by subject + status.
            $blueprint->index(['reported_type', 'reported_id', 'status']);

            // Duplicate detection looks up subject + reporter.
            $blueprint->index(['reported_type', 'reported_id', 'reporter_type', 'reporter_id'], "{$tableName}_dedupe_index");
        });
    }
};
