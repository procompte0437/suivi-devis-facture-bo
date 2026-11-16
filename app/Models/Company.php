<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $guarded = [];

    protected $hidden = ['logo_path'];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $host = request()?->getSchemeAndHttpHost() ?: rtrim((string) config('app.url'), '/');
        $version = $this->updated_at?->getTimestamp() ?? 0;

        return $host.'/storage/'.$this->logo_path.'?v='.$version;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function staff(): HasMany
    {
        return $this->hasMany(StaffMember::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
