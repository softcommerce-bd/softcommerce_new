<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    use HasFactory;
    protected $guarded = [];

      // One category has many expenses
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'expense_category_id');
    }

    // Parent category
    public function parent()
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    // Child categories
    public function children()
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id');
    }
}
