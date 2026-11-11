<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Quote extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $appends = ['display_status'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'decided_at' => 'datetime',
            'tax_rate' => 'float',
            'discount' => 'float',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
        ];
    }

    public function getDisplayStatusAttribute(): ?string
    {
        if ($this->status === 'envoye' && $this->valid_until && $this->valid_until->copy()->endOfDay()->isPast()) {
            return 'expire';
        }

        return $this->status;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(QuoteLine::class)->orderBy('position');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
