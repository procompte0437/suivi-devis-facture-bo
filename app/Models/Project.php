<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'progress' => 'integer',
            'budget' => 'float',
            'start_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('position');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class)->orderBy('position');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest();
    }

    public function budgetLines(): HasMany
    {
        return $this->hasMany(BudgetLine::class)->orderBy('position');
    }
}
