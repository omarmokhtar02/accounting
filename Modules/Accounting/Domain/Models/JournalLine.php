<?php

namespace Modules\Accounting\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Infrastructure\BelongsToTenant;

class JournalLine extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'company_id', 'journal_entry_id', 'account_id', 'debit', 'credit', 'description',
        'txn_currency', 'txn_debit', 'txn_credit', 'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
