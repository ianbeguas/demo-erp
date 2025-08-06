<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'customer_id',
        'warehouse_id',
        'number',
        'type',
        'payment_method',
        'invoice_date',
        'due_date',
        'payment_date',
        'discount_rate',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'shipping_method',
        'shipping_cost',
        'subtotal',
        'total_amount',
        'currency',
        'status',
        'delivery_status',
        'notes',
        'created_by_user_id',
        'is_credit',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'discount_rate' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $appends = ['name', 'shipment_status'];

    public function getNameAttribute()
    {
        return $this->number;
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function details()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function getShipmentStatusAttribute()
    {
        return $this->shipments()->latest()->first()?->status ?? 'pending';
    }

    protected static function booted()
    {
        static::addGlobalScope('company_filter', function (Builder $builder) {
            $user = Auth::user();

            if ($user && !$user->hasRole('super-admin')) {
                $builder->where(function ($query) use ($user) {
                    $query->where('company_id', $user->company_id)
                        ->orWhereNull('company_id');
                });
            }
        });

        static::creating(function ($invoice) {
            if (empty($invoice->number)) {
                $company = \App\Models\Company::find($invoice->company_id);

                if ($company) {
                    // Extract base prefix from company name
                    $basePrefix = strtoupper(substr(preg_replace('/\s+/', '', $company->name), 0, 3));

                    // Get all companies with the same base prefix
                    $matchingCompanies = \App\Models\Company::all()->filter(function ($comp) use ($basePrefix) {
                        return strtoupper(substr(preg_replace('/\s+/', '', $comp->name), 0, 3)) === $basePrefix;
                    })->values();

                    // Determine final prefix
                    if ($matchingCompanies->count() === 1) {
                        $finalPrefix = $basePrefix;
                    } else {
                        $index = $matchingCompanies->search(function ($comp) use ($company) {
                            return $comp->id === $company->id;
                        });
                        $finalPrefix = $basePrefix . ($index !== false ? $index + 1 : 1);
                    }

                    // Count existing invoices for the company
                    $count = self::where('company_id', $invoice->company_id)->withTrashed()->count() + 1;

                    // Format invoice number
                    $invoice->number = sprintf('%s-SI-%06d', $finalPrefix, $count);
                } else {
                    $invoice->number = 'UNK-SI-' . sprintf('%06d', rand(1, 999999));
                }
            }
        });

        static::created(function ($invoice) {
            \DB::afterCommit(function () use ($invoice) {
                $invoice->registerJournalAfterCommit();
        
                // Create Shipment
                $shipment = Shipment::create([
                    'company_id' => $invoice->company_id,
                    'invoice_id' => $invoice->id,
                    'status' => 'pending',
                    'created_by_user_id' => $invoice->created_by_user_id,
                ]);
        
                // Reload invoice details to ensure they are available
                $invoiceDetails = InvoiceDetail::where('invoice_id', $invoice->id)->get();
        
                foreach ($invoiceDetails as $detail) {
                    ShipmentDetail::create([
                        'shipment_id' => $shipment->id,
                        'invoice_detail_id' => $detail->id,
                        'qty' => $detail->qty,
                        'status' => 'pending',
                    ]);
                }
            });
        });

        static::updated(function ($invoice) {
            if ($invoice->status === 'fully-paid') {
                $invoice->registerJournalAfterCommit();
            }
        });
    }

    public function registerJournalAfterCommit()
    {
        \DB::afterCommit(function () {
            $invoice = self::find($this->id)->load(['details.warehouseProduct', 'paymentMethodDetails']);

            if ($invoice->status !== 'fully-paid') return;
            if (\App\Models\JournalEntry::where('reference_number', $invoice->number)->exists()) return;

            // Check if any payment method detail is approved
            $hasApprovedPayment = $invoice->paymentMethodDetails->contains('status', 'approved');
            if (!$hasApprovedPayment) return;

            $entry = \App\Models\JournalEntry::create([
                'company_id' => $invoice->company_id,
                'reference_number' => $invoice->number,
                'reference_date' => $invoice->invoice_date,
                'remarks' => 'POS Invoice Payment: ' . $invoice->number,
                'created_by_user_id' => $invoice->created_by_user_id,
            ]);

            $revenueAccount = \App\Models\Account::where('name', 'Sales Revenue')->firstOrFail();
            $taxAccount = \App\Models\Account::where('name', 'Taxes Payable')->first();
            $cogsAccount = \App\Models\Account::where('name', 'Cost of Goods Sold (COGS)')->first();
            $inventoryAccount = \App\Models\Account::where('name', 'Inventory')->first();
            $shippingRevenueAccount = \App\Models\Account::where('name', 'Shipping Revenue')->first();
            $salesDiscountAccount = \App\Models\Account::where('name', 'Sales Discounts')->first();

            // 🔁 Handle each approved payment method detail (DEBIT Cash/Bank)
            $totalPaid = 0;
            foreach ($invoice->paymentMethodDetails as $methodDetail) {
                // Skip if not approved
                if ($methodDetail->status !== 'approved') continue;

                $paymentMethodCode = $methodDetail->payment_method;
                $amount = $methodDetail->amount ?? 0;
                $totalPaid += $amount;

                $paymentMethod = \App\Models\PaymentMethod::where('code', $paymentMethodCode)->first();
                $paymentAccount = $paymentMethod?->account;

                if (!$paymentAccount) {
                    \Log::warning('Missing account for invoice payment method:', [
                        'payment_method' => $paymentMethodCode,
                        'invoice_id' => $invoice->id,
                    ]);
                    continue;
                }

                \App\Models\JournalEntryDetail::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $paymentAccount->id,
                    'name' => 'Received via ' . $paymentMethod->name . ' for invoice ' . $invoice->number,
                    'debit' => $amount,
                    'credit' => 0,
                ]);
            }

            // Credit: Sales Revenue (subtotal before tax and shipping)
            \App\Models\JournalEntryDetail::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $revenueAccount->id,
                'name' => 'Sales income for invoice ' . $invoice->number,
                'debit' => 0,
                'credit' => $invoice->subtotal,
            ]);

            // Debit: Sales Discount (if there's a discount)
            if ($invoice->discount_amount > 0 && $salesDiscountAccount) {
                \App\Models\JournalEntryDetail::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $salesDiscountAccount->id,
                    'name' => 'Sales discount for invoice ' . $invoice->number,
                    'debit' => $invoice->discount_amount,
                    'credit' => 0,
                ]);
            }

            // Credit: Shipping Revenue (if there's shipping cost)
            if ($invoice->shipping_cost > 0 && $shippingRevenueAccount) {
                \App\Models\JournalEntryDetail::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $shippingRevenueAccount->id,
                    'name' => 'Shipping revenue for invoice ' . $invoice->number,
                    'debit' => 0,
                    'credit' => $invoice->shipping_cost,
                ]);
            }

            // Credit: VAT/Tax
            if ($invoice->tax_amount > 0 && $taxAccount) {
                \App\Models\JournalEntryDetail::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $taxAccount->id,
                    'name' => 'VAT for invoice ' . $invoice->number,
                    'debit' => 0,
                    'credit' => $invoice->tax_amount,
                ]);
            }

            // Handle COGS and Inventory
            if ($cogsAccount && $inventoryAccount) {
                $totalCOGS = 0;

                foreach ($invoice->details as $detail) {
                    $cost = $detail->warehouseProduct?->last_cost ?? 0;
                    $totalCOGS += $cost * $detail->qty;
                }

                if ($totalCOGS > 0) {
                    // Debit: COGS
                    \App\Models\JournalEntryDetail::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $cogsAccount->id,
                        'name' => 'COGS for invoice ' . $invoice->number,
                        'debit' => $totalCOGS,
                        'credit' => 0,
                    ]);

                    // Credit: Inventory
                    \App\Models\JournalEntryDetail::create([
                        'journal_entry_id' => $entry->id,
                        'account_id' => $inventoryAccount->id,
                        'name' => 'Inventory reduction for invoice ' . $invoice->number,
                        'debit' => 0,
                        'credit' => $totalCOGS,
                    ]);
                }
            }
        });
    }

    public function paymentMethodDetails()
    {
        return $this->hasMany(InvoicePaymentMethodDetail::class);
    }

    public function approvedPayments()
    {
        return $this->paymentMethodDetails()->where('status', 'approved');
    }

    public function getTotalPaidAttribute()
    {
        return $this->approvedPayments()->sum('amount');
    }

    public function getRemainingBalanceAttribute()
    {
        return $this->total_amount - $this->total_paid;
    }

    public function invoiceSerials()
    {
        return $this->hasMany(InvoiceSerial::class);
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }

    public function shipmentDetails()
    {
        return $this->hasMany(ShipmentDetail::class);
    }
}
