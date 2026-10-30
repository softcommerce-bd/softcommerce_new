<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CostCenter extends Model
{
    use SoftDeletes;

    protected $table = 'cost_centers';

    protected $fillable = [
        'code',
        'name',
        'description',
        'parent_id',
        'status',
        'manager_id',
        'budget',
        'current_expense',
        'address',
        'phone',
        'email',
        'created_by'
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'current_expense' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Constants for status
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * Get all statuses
     */
    public static function getStatuses()
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
        ];
    }

    /**
     * Get parent cost center
     */
    public function parent()
    {
        return $this->belongsTo(CostCenter::class, 'parent_id');
    }

    /**
     * Get child cost centers
     */
    public function children()
    {
        return $this->hasMany(CostCenter::class, 'parent_id');
    }

    /**
     * Get manager user
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get balance transfers for this cost center
     */
    public function balanceTransfers()
    {
        return $this->hasMany(BalanceTransfer::class, 'cost_center_id');
    }

    /**
     * Get expenses for this cost center
     */
    public function expenses()
    {
        return $this->hasMany(ExpensePayment::class, 'cost_center_id');
    }

    /**
     * Get incomes for this cost center
     */
    public function incomes()
    {
        return $this->hasMany(Income::class, 'cost_center_id');
    }

    /**
     * Get vouchers for this cost center
     */
    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'cost_center_id');
    }

    /**
     * Get user who created the cost center
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active cost centers
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for inactive cost centers
     */
    public function scopeInactive($query)
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    /**
     * Scope for parent cost centers
     */
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Get remaining budget
     */
    public function getRemainingBudgetAttribute()
    {
        return $this->budget - $this->current_expense;
    }

    /**
     * Get formatted budget
     */
    public function getFormattedBudgetAttribute()
    {
        return number_format($this->budget, 2) . ' ৳';
    }

    /**
     * Get formatted current expense
     */
    public function getFormattedCurrentExpenseAttribute()
    {
        return number_format($this->current_expense, 2) . ' ৳';
    }

    /**
     * Get formatted remaining budget
     */
    public function getFormattedRemainingBudgetAttribute()
    {
        return number_format($this->remaining_budget, 2) . ' ৳';
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute()
    {
        $statuses = self::getStatuses();
        return $statuses[$this->status] ?? ucfirst($this->status);
    }

    /**
     * Get status badge class
     */
    public function getStatusBadgeClassAttribute()
    {
        return $this->status == self::STATUS_ACTIVE ? 'badge-success' : 'badge-danger';
    }

    /**
     * Get full path of cost center hierarchy
     */
    public function getFullPathAttribute()
    {
        $path = [$this->name];
        $parent = $this->parent;
        
        while ($parent) {
            array_unshift($path, $parent->name);
            $parent = $parent->parent;
        }
        
        return implode(' / ', $path);
    }

    /**
     * Generate cost center code
     */
    public static function generateCode()
    {
        $lastCostCenter = self::orderBy('id', 'desc')->first();
        
        if ($lastCostCenter && $lastCostCenter->code) {
            $lastNumber = intval(substr($lastCostCenter->code, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return 'CC-' . $newNumber;
    }
}