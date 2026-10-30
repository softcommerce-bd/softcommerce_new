<?php
// app/Models/StockAdjustment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'reference_no',
        'transaction_date',
        'adjustment_type',
        'total_amount',
        'total_amount_recovered',
        'additional_notes',
        'created_by'
    ];

    protected $casts = [
        'transaction_date' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines()
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }

    public static function generateReferenceNo()
    {
        $year = date('Y');
        $lastRecord = self::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
        
        $newNumber = $lastRecord ? intval(substr($lastRecord->reference_no, -4)) + 1 : 1;
        
        return 'SA' . $year . '/' . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }
}