<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'float',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'company_name' => 'Atelier Orée',
            'tagline' => 'Devis, factures et projets',
            'country' => 'Gabon',
            'tax_rate' => 18,
            'currency' => 'XAF',
            'quote_prefix' => 'DEV',
            'invoice_prefix' => 'FAC',
        ]);
    }
}
