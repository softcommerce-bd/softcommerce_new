<?php

namespace App\Http\Controllers;

use App\Exports\ExpensesExport;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Maatwebsite\Excel\Facades\Excel;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the expense categories.
     */

    public function index(Request $request)
    {
        $query = ExpenseCategory::with('parent');

        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $categories = $query->latest()->paginate(10);

        return view('backend.expense-categories.index', compact('categories'));
    }

    /**
     * Store a newly created expense category.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:expense_categories,id',
        ]);


        ExpenseCategory::create([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
        ]);

        flash(translate('Expense category created successfully!'))->success();
        return redirect()->back();
    }

    /**
     * Display the specified expense category.
     */
    public function show($id)
    {
        $category = ExpenseCategory::with('parent', 'children')->findOrFail($id);
        return response()->json($category);
    }

    /**
     * Show the form for editing the specified expense category.
     */
    public function edit($id)
    {
        $category = ExpenseCategory::findOrFail($id);
        $categories = ExpenseCategory::where('id', '!=', $id)->get();

        return response()->json([
            'category' => $category,
            'categories' => $categories
        ]);
    }

    /**
     * Update the specified expense category.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:expense_categories,id',
        ]);

        $category = ExpenseCategory::findOrFail($id);

        // Prevent setting self as parent
        if ($request->parent_id == $id) {
            flash(translate('Cannot set category as its own parent!'))->error();
            return redirect()->route('expense-categories');
        }


        $category->update([
            'name' => $request->name,
            'parent_id' => $request->parent_id,
        ]);


        flash(translate('Expense category Updated successfully!'))->success();
        return redirect()->route('expense-categories');
    }

    /**
     * Remove the specified expense category.
     */
    public function destroy($id)
    {
        $category = ExpenseCategory::findOrFail($id);

        // Check if category has child categories
        if ($category->children()->count() > 0) {
            flash(translate('Cannot delete category with child categories!'))->error();
            return redirect()->route('expense-categories');
        }

        // Check if category has expenses
        if ($category->expenses()->count() > 0) {
            flash(translate('Cannot delete category with associated expenses!'))->error();
            return redirect()->route('expense-categories');
        }

        $category->delete();

        flash(translate('Expense category deleted successfully!'))->success();
        return redirect()->route('expense-categories');
    }

    /**
     * Update featured status via AJAX.
     */
    public function updateFeatured(Request $request)
    {
        $category = ExpenseCategory::findOrFail($request->id);
        $category->featured = $request->status;
        $category->save();

        return response()->json(['success' => true]);
    }

    // Expense all routes 
    public function expenseIndex(Request $request)
    {
        $expenses = Expense::with('category');
        // dd($request->all());
        // Filter by date range
        if ($request->has('date') && $request->date) {
            $dateParts = explode(' to ', $request->date);

            if (count($dateParts) == 2) {
                // It's a date range
                $start = date('Y-m-d', strtotime($dateParts[0])) . ' 00:00:00';
                $end = date('Y-m-d', strtotime($dateParts[1])) . ' 23:59:59';
                $expenses->whereBetween('date', [$start, $end]);
            } else {
                // It's a single date
                $expenses->whereDate('date', date('Y-m-d', strtotime($request->date)));
            }
        }

        // Filter by category
        if ($request->has('expense_category_id') && $request->expense_category_id) {
            $expenses->where('expense_category_id', $request->expense_category_id);
        }

        // Filter by title search
        if ($request->has('search') && $request->search) {
            $expenses->where('title', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'date');
        $sortOrder = $request->get('sort_order', 'desc');
        $expenses->orderBy($sortBy, $sortOrder);

        $expenses = $expenses->paginate(10);
        $categories = ExpenseCategory::with('parent')->get();
        $date = $request->date ?? '';
        $selectedCategory = $request->expense_category_id ?? '';
        $search = $request->search ?? '';

        return view('backend.expenses.index', compact('expenses', 'categories', 'date', 'selectedCategory', 'sortBy', 'sortOrder', 'search'));
    }

    /**
     * Show form for creating new expense.
     */
    public function expenseCreate()
    {
        $categories = ExpenseCategory::with('parent')->latest()->get();
        return view('backend.expenses.create', compact('categories'));
    }

    /**
     * Store a newly created expense.
     */
    public function expenseStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        Expense::create($request->all());

        return redirect()->route('expenses.index')->with('success', translate('Expense created successfully!'));
    }

    /**
     * Display the specified expense.
     */
    public function expenseShow($id)
    {
        $expense = Expense::with('category')->findOrFail($id);
        return response()->json($expense);
    }

    /**
     * Show form for editing expense.
     */
    public function expenseEdit($id)
    {
        $expense = Expense::findOrFail($id);
        $categories = ExpenseCategory::with('parent')->get();

        if (request()->ajax()) {
            return response()->json([
                'expense' => $expense,
                'categories' => $categories
            ]);
        }

        return view('backend.expenses.edit', compact('expense', 'categories'));
    }

    /**
     * Update the specified expense.
     */
    public function expenseUpdate(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        $expense = Expense::findOrFail($id);
        $expense->update($request->all());

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => translate('Expense updated successfully!')]);
        }

        return redirect()->route('expenses.index')->with('success', translate('Expense updated successfully!'));
    }

    /**
     * Remove the specified expense.
     */
    public function expenseDestroy($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();

        if (request()->ajax()) {
            return response()->json(['success' => true, 'message' => translate('Expense deleted successfully!')]);
        }

        return redirect()->route('expenses.index')->with('success', translate('Expense deleted successfully!'));
    }

    /**
     * Export expenses to Excel.
     */
    public function expenseExport(Request $request)
    {
        // dd($request->all());
        $filters = [
            'date' => $request->date,
            'expense_category_id' => $request->expense_category_id,
            'search' => $request->search,
            'sort_by' => $request->sort_by,
            'sort_order' => $request->sort_order,
        ];

        return Excel::download(new ExpensesExport($filters), 'expenses-' . date('Y-m-d') . '.xlsx');
    }
}
