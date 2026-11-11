<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToCompany;

    protected $guarded = [];

    protected $appends = ['display_status', 'balance'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'tax_rate' => 'float',
            'discount' => 'float',
            'subtotal' => 'float',
            'tax_amount' => 'float',
            'total' => 'float',
            'amount_paid' => 'float',
        ];
    }

    public function getBalanceAttribute(): float
    {
        return round(max(0, (float) $this->total - (float) $this->amount_paid), 2);
    }

    public function getDisplayStatusAttribute(): ?string
    {
        if (
            in_array($this->status, ['emise', 'partielle'], true)
            && $this->due_date
            && $this->due_date->copy()->endOfDay()->isPast()
            && (float) $this->amount_paid < (float) $this->total
        ) {
            return 'en_retard';
        }

        return $this->status;
    }

    public function refreshPaymentStatus(): void
    {
        if (in_array($this->status, ['brouillon', 'annulee'], true)) {
            return;
        }

        $paid = round((float) $this->payments()->sum('amount'), 2);
        $this->amount_paid = $paid;

        if ($paid <= 0) {
            $this->status = 'emise';
        } elseif ($paid + 0.009 >= (float) $this->total) {
            $this->status = 'payee';
            $this->amount_paid = (float) $this->total;
        } else {
            $this->status = 'partielle';
        }

        $this->save();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderByDesc('paid_at');
    }
}
