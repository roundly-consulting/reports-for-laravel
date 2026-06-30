<?php

declare(strict_types=1);

use RoundlyConsulting\Reports\Models\Report;

return [

    /*
    |--------------------------------------------------------------------------
    | Report Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model used to store reports. Override this with your own
    | model (extending the package's Report) if you need custom behaviour.
    |
    */

    'model' => Report::class,

    /*
    |--------------------------------------------------------------------------
    | Reports Table
    |--------------------------------------------------------------------------
    |
    | The database table reports are stored in.
    |
    */

    'table' => 'reports',

    /*
    |--------------------------------------------------------------------------
    | Morph Key Type
    |--------------------------------------------------------------------------
    |
    | The key type used for the polymorphic reporter / reported / resolved_by
    | columns. Use "uuid" when your reporter and reportable models use UUID
    | primary keys, otherwise leave it as "bigint".
    |
    | Supported: "bigint", "uuid"
    |
    */

    'morph_key_type' => 'bigint',

    /*
    |--------------------------------------------------------------------------
    | Reasons
    |--------------------------------------------------------------------------
    |
    | The allowed reason slugs a report can be filed under. Defaults mirror the
    | Reason enum; you may add your own custom slugs here. Labels are derived from
    | the slug via the enums Helpers trait (Reason::readable()).
    |
    */

    'default_reason' => 'other',

    'reasons' => ['spam', 'abuse', 'harassment', 'inappropriate', 'misinformation', 'other'],

    'allow_unknown_reasons' => false,

    /*
    |--------------------------------------------------------------------------
    | Duplicate Prevention
    |--------------------------------------------------------------------------
    |
    | When enabled, the same reporter (or guest identifier) cannot file more
    | than one report against the same subject. The "duplicate_scope" controls
    | whether this considers only open (non-terminal) reports or any report
    | ever filed.
    |
    | Supported scopes: "open", "any"
    |
    */

    'prevent_duplicates' => true,

    'duplicate_scope' => 'open',

    /*
    |--------------------------------------------------------------------------
    | Status Transitions
    |--------------------------------------------------------------------------
    |
    | When strict transitions are enabled, only the moves declared in the
    | Status enum's transition table are allowed; any other transition throws
    | an InvalidStatusTransitionException.
    |
    */

    'strict_transitions' => true,

    /*
    |--------------------------------------------------------------------------
    | Threshold Auto-Action
    |--------------------------------------------------------------------------
    |
    | When set, a ReportThresholdReached event fires the moment a subject's
    | open report count reaches this number (fired once on crossing). Set to
    | null to disable threshold notifications entirely.
    |
    */

    'threshold' => null,

    /*
    |--------------------------------------------------------------------------
    | Pruning
    |--------------------------------------------------------------------------
    |
    | The default age (in days) used by the reports:prune command when no
    | --days option is supplied. Set to null to require an explicit --days.
    |
    */

    'prune_after_days' => null,

    /*
    |--------------------------------------------------------------------------
    | Moderation
    |--------------------------------------------------------------------------
    |
    | Multi-moderator sign-off is routed through approvals-for-laravel. These
    | defaults seed the Reports::moderate() builder when no rule/quorum is given.
    |
    | "default_rule" is an ApprovalRule value: "unanimous", "quorum", "any" or
    | "weighted". "default_quorum" is the approval count for the "quorum" rule
    | (null = require every declared moderator).
    |
    */

    'moderation' => [
        'default_rule' => 'unanimous',
        'default_quorum' => null,
    ],

];
