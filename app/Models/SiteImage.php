<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteImage extends Model
{
    protected $guarded = [];

    protected $appends = ['url'];

    /** Adresse publique du fichier, construite sur l'hôte de la requête (comme les logos). */
    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        $host = request()?->getSchemeAndHttpHost() ?: rtrim((string) config('app.url'), '/');

        return $host.'/storage/'.$this->path;
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
