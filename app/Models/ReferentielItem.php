<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferentielItem extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
    ];

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'societe_id');
    }
}
