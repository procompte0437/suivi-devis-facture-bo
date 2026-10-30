<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

class DocumentNumbers
{
    /**
     * @param  class-string<Model>  $model
     */
    public static function next(string $prefix, string $model, string $column = 'number'): string
    {
        $year = now()->year;
        $last = $model::query()
            ->withoutGlobalScope('company')
            ->where($column, 'like', $prefix.'-'.$year.'-%')
            ->orderByDesc($column)
            ->value($column);

        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $sequence);
    }

    /**
     * @param  class-string<Model>  $model
     */
    public static function code(string $prefix, string $model, string $column = 'code'): string
    {
        $last = $model::query()->withoutGlobalScope('company')->orderByDesc('id')->value($column);
        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s-%03d', $prefix, $sequence);
    }
}
