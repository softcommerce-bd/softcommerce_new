<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;
    protected $guarded = [];
    
    public function purchases()
    {
        return $this->hasMany(Purchase::class, 'supplier_id', 'id');
    }
    
    // accessor
    public function getTotalPaymentDueAttribute()
    {
        return $this->purchases()->sum('total_payment_due');
    }

//     public function getDueAmountAttribute()
// {
//     // Total payments made to this supplier
//     $totalPaid = $this->paid_amount ?? 0;
    
    
//     return $this->opening_balance - $totalPaid;
// }

// Relationship with supplier_payments
public function payments()
{
    return $this->hasMany(SupplierPayment::class);
}

// In your Supplier model
public static function generateContactId()
{
    $lastSupplier = self::orderBy('id', 'desc')->first();
    
    if (!$lastSupplier || empty($lastSupplier->contact_id)) {
        return 'Ref-10000';
    }
    
    // Extract number from pattern Ref-XXXXX
    if (preg_match('/Ref-(\d+)/', $lastSupplier->contact_id, $matches)) {
        $nextNumber = intval($matches[1]) + 1;
        return 'Ref-' . $nextNumber;
    }
    
    // Fallback if pattern doesn't match
    return 'Ref-10000';
}


}
