<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntryDetail extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'name',
        'debit',
        'credit',
        'remarks',
    ];

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}