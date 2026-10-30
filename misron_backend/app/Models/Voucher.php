<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'vouchers';
    
    protected $fillable = [
        'voucher_no',
        'type',
        'date',
        'amount',
        'description',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'reference_no'
    ];
    
    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // Voucher Type Constants
    const TYPE_CP = 'CP'; // Cash Payment
    const TYPE_BP = 'BP'; // Bank Payment
    const TYPE_CR = 'CR'; // Cash Receipt
    const TYPE_BR = 'BR'; // Bank Receipt
    const TYPE_JV = 'JV'; // Journal Voucher
    const TYPE_CV = 'CV'; // Contra Voucher
    
    // Status Constants
    const STATUS_DRAFT = 'draft';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_CANCELLED = 'cancelled';
    
    /**
     * Get all voucher types
     */
    public static function getVoucherTypes()
    {
        return [
            self::TYPE_CP => 'Cash Payment',
            self::TYPE_BP => 'Bank Payment',
            self::TYPE_CR => 'Cash Receipt',
            self::TYPE_BR => 'Bank Receipt',
            self::TYPE_JV => 'Journal Voucher',
            self::TYPE_CV => 'Contra Voucher',
        ];
    }
    
    /**
     * Get voucher details
     */
    public function details()
    {
        return $this->hasMany(VoucherDetail::class, 'voucher_id');
    }
    
    /**
     * Get creator user
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    
    /**
     * Get approver user
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
    
    /**
     * Post voucher to accounts (Update Chart of Account balances)
     * Uses proper accounting rules based on account type
     */
    public function postToAccounts()
    {
        foreach ($this->details as $detail) {
            $account = ChartOfAccount::find($detail->account_id);
            if ($account) {
                $oldBalance = $account->current_balance;

                switch ($account->type) {
                    case 'asset':
                    case 'expense':
                        $account->current_balance += $detail->debit;
                        $account->current_balance -= $detail->credit;
                        break;
                    case 'liability':
                    case 'equity':
                    case 'income':
                        $account->current_balance -= $detail->debit;
                        $account->current_balance += $detail->credit;
                        break;
                    default:
                        $account->current_balance += $detail->debit;
                        $account->current_balance -= $detail->credit;
                        break;
                }
                $account->save();

                AccountStatementLog::create([
                    'account_id' => $detail->account_id,
                    'transaction_date' => $this->date,
                    'reference_no' => $this->voucher_no,
                    'transaction_type' => $this->type,
                    'debit' => $detail->debit,
                    'credit' => $detail->credit,
                    'balance' => $account->current_balance,
                    'description' => $detail->narration ?? $this->description,
                    'voucher_id' => $this->id,
                ]);
            }
        }
    }
    
    /**
     * Reverse voucher from accounts (For update/delete)
     * Reverses the effect of postToAccounts() based on account type
     */
    public function reverseFromAccounts()
    {
        foreach ($this->details as $detail) {
            $account = ChartOfAccount::find($detail->account_id);
            if ($account) {
                switch ($account->type) {
                    case 'asset':
                    case 'expense':
                        $account->current_balance -= $detail->debit;
                        $account->current_balance += $detail->credit;
                        break;
                    case 'liability':
                    case 'equity':
                    case 'income':
                        $account->current_balance += $detail->debit;
                        $account->current_balance -= $detail->credit;
                        break;
                    default:
                        $account->current_balance -= $detail->debit;
                        $account->current_balance += $detail->credit;
                        break;
                }
                $account->save();

                AccountStatementLog::where('voucher_id', $this->id)
                    ->where('account_id', $detail->account_id)
                    ->delete();
            }
        }
    }
    
    /**
     * Generate voucher number
     */
    // public static function generateVoucherNo($type)
    // {
    //     $prefixes = [
    //         'CP' => 'CP',
    //         'BP' => 'BP',
    //         'CR' => 'CR',
    //         'BR' => 'BR',
    //         'JV' => 'JV',
    //         'CV' => 'CV',
    //     ];
        
    //     $prefix = $prefixes[$type] ?? 'VCH';
    //     $year = date('y');
    //     $month = date('m');
        
    //     $lastVoucher = self::where('voucher_no', 'like', $prefix . '-' . $year . $month . '%')
    //         ->orderBy('id', 'desc')
    //         ->first();
        
    //     if ($lastVoucher) {
    //         $lastNumber = intval(substr($lastVoucher->voucher_no, -4));
    //         $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    //     } else {
    //         $newNumber = '0001';
    //     }
        
    //     return $prefix . '-' . $year . $month . $newNumber;
    // }

    /**
 * Generate voucher number
 */
public static function generateVoucherNo($type)
{
    $prefixes = [
        'CP' => 'CP',
        'BP' => 'BP',
        'CR' => 'CR',
        'BR' => 'BR',
        'JV' => 'JV',
        'CV' => 'CV',
    ];
    
    $prefix = $prefixes[$type] ?? 'VCH';
    $year = date('y');
    $month = date('m');
    
    $lastVoucher = self::withTrashed()
        ->where('voucher_no', 'like', $prefix . '-' . $year . $month . '%')
        ->orderBy('id', 'desc')
        ->first();
    
    if ($lastVoucher) {
        $lastNumber = intval(substr($lastVoucher->voucher_no, -4));
        $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $newNumber = '0001';
    }
    
    return $prefix . '-' . $year . $month . $newNumber;
}
    
    /**
     * Get type label
     */
    public function getTypeLabelAttribute()
    {
        $types = self::getVoucherTypes();
        return $types[$this->type] ?? $this->type;
    }
    
    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $statuses = [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_CANCELLED => 'Cancelled',
        ];
        return $statuses[$this->status] ?? ucfirst($this->status);
    }
    
    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        switch ($this->status) {
            case self::STATUS_APPROVED:
                return 'badge-success';
            case self::STATUS_DRAFT:
                return 'badge-warning';
            case self::STATUS_REJECTED:
            case self::STATUS_CANCELLED:
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    }
}