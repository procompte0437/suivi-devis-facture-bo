<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (CompanyContext::isEmpty()) {
                $builder->whereRaw('1 = 0');

                return;
            }

            if ($id = CompanyContext::id()) {
                $builder->where($builder->getModel()->getTable().'.company_id', $id);
            }
        });

        static::creating(function ($model) {
            if (! $model->company_id && $id = CompanyContext::id()) {
                $model->company_id = $id;
            }
        });
    }
}
