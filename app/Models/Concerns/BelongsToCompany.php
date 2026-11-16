<?php

namespace App\Models\Concerns;

use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (CompanyContext::isAll()) {
                return;
            }

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

            if (! $model->company_id && CompanyContext::isAll()) {
                throw ValidationException::withMessages([
                    'company' => 'Choisissez une entreprise dans le menu avant d’enregistrer.',
                ]);
            }
        });
    }

    /** Entreprise propriétaire de la pièce. */
    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Company::class);
    }
}
