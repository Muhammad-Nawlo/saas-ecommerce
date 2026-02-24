<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/** Removes sensitive attributes from arrays before logging. */
final class AuditAttributeFilter
{
    public static function filter(array $attributes): array
    {
        $excluded = config('audit.excluded_attributes', []);
        foreach ($excluded as $key) {
            Arr::forget($attributes, $key);
        }
        return $attributes;
    }

    /** Get changed attributes with old/new values, excluding sensitive. Call from updating() event (before save). */
    public static function diff(Model $model): array
    {
        $old = self::filter($model->getOriginal());
        $new = self::filter($model->getAttributes());
        $changes = [];
        $allKeys = array_unique(array_merge(array_keys($old), array_keys($new)));
        foreach ($allKeys as $key) {
            $oldVal = $old[$key] ?? null;
            $newVal = $new[$key] ?? null;
            if ($oldVal != $newVal) {
                $changes[$key] = ['old' => $oldVal, 'new' => $newVal];
            }
        }
        return $changes;
    }
}
