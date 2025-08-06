<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WarehouseStockTransfer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'transfer_date',
        'origin_warehouse_id',
        'destination_warehouse_id',
        'status',
        'remarks',
        'created_by_user_id'
    ];

    public function originWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'origin_warehouse_id');
    }

    public function destinationWarehouse()
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function details()
    {
        return $this->hasMany(WarehouseStockTransferDetail::class);
    }

    protected static function booted()
{
    static::creating(function ($wst) {
        if (empty($wst->number)) {
            // First get the warehouse
            $originWarehouse = \App\Models\Warehouse::find($wst->origin_warehouse_id);
            if (!$originWarehouse) {
                throw new \Exception('Origin warehouse not found');
            }

            $company = \App\Models\Company::find($originWarehouse->company_id);

            if ($company) {
                // Extract base prefix
                $basePrefix = strtoupper(substr(preg_replace('/\s+/', '', $company->name), 0, 3));

                // Get all companies with the same base prefix
                $matchingCompanies = \App\Models\Company::all()->filter(function ($comp) use ($basePrefix) {
                    return strtoupper(substr(preg_replace('/\s+/', '', $comp->name), 0, 3)) === $basePrefix;
                })->values();

                if ($matchingCompanies->count() === 1) {
                    $finalPrefix = $basePrefix;
                } else {
                    $index = $matchingCompanies->search(fn ($comp) => $comp->id === $company->id);
                    $finalPrefix = $basePrefix . ($index !== false ? $index + 1 : 1);
                }

                // 🔒 Find last number safely
                $latest = self::withTrashed()
                    ->where('number', 'like', "$finalPrefix-WST-%")
                    ->orderByDesc('id')
                    ->first();

                $lastNumber = optional($latest)->number;
                $lastIncrement = 0;

                if ($lastNumber && preg_match('/\d+$/', $lastNumber, $matches)) {
                    $lastIncrement = (int) $matches[0];
                }

                $nextNumber = $lastIncrement + 1;

                $wst->number = sprintf('%s-WST-%06d', $finalPrefix, $nextNumber);
            } else {
                $wst->number = 'UNK-WST-' . sprintf('%06d', rand(1, 999999));
            }
        }

        if (empty($wst->status)) {
            $wst->status = 'pending';
        }
    });
}

}
