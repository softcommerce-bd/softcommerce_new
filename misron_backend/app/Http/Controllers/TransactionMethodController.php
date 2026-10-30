<?php

namespace App\Http\Controllers;

use App\Models\TransactionMethod;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionMethodController extends Controller
{
    /**
     * Get all methods (AJAX).
     */
    public function getMethods(Request $request)
    {
        $query = TransactionMethod::query();

        if ($request->has('deposit_type')) {
            $query->where('deposit_type', $request->deposit_type);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('with_trashed')) {
            $query->withTrashed();
        }

        $methods = $query->latest()->get();

        return response()->json(['success' => true, 'data' => $methods]);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255|unique:transaction_methods,name',
            'type'              => 'required|in:online,card,mobile,cash,bank',
            'deposit_type'      => 'required|in:auto,manual',
            'charge'            => 'nullable|numeric|min:0|max:100',
            'bank_account_id'   => 'required_if:deposit_type,auto|exists:chart_of_accounts,id',
            'source_account_id' => 'required_if:deposit_type,auto|exists:chart_of_accounts,id',
            'status'            => 'in:active,inactive',
            'description'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $method = TransactionMethod::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment method created successfully!',
            'data' => $method->load(['bankAccount', 'sourceAccount'])
        ]);
    }

    public function update(Request $request, $id)
    {
        $method = TransactionMethod::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255|unique:transaction_methods,name,' . $id,
            'type'              => 'required|in:online,card,mobile,cash,bank',
            'deposit_type'      => 'required|in:auto,manual',
            'charge'            => 'nullable|numeric|min:0|max:100',
            'bank_account_id'   => 'required_if:deposit_type,auto|exists:chart_of_accounts,id',
            'source_account_id' => 'required_if:deposit_type,auto|exists:chart_of_accounts,id',
            'status'            => 'in:active,inactive',
            'description'       => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $method->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully!',
            'data' => $method->load(['bankAccount', 'sourceAccount'])
        ]);
    }

    public function destroy($id)
    {
        $method = TransactionMethod::findOrFail($id);
        $method->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully!'
        ]);
    }

    public function getMethod($id)
    {
        $method = TransactionMethod::with(['bankAccount', 'sourceAccount'])->findOrFail($id);
        return response()->json(['success' => true, 'data' => $method]);
    }

    /**
     * Restore soft deleted (AJAX).
     */
    public function restore($id)
    {
        $method = TransactionMethod::withTrashed()->findOrFail($id);
        $method->restore();

        return response()->json([
            'success' => true,
            'message' => 'Payment method restored successfully.'
        ]);
    }

    /**
     * Get bank accounts for dropdown (AJAX).
     */
    public function getBankAccounts()
    {
        $accounts = ChartOfAccount::where('sub_type', 'bank')
            ->where('status', 'active')
            ->where('is_deleted', 0)   // adjust if your soft‑delete flag differs
            ->get(['id', 'name', 'account_number', 'current_balance']);

        return response()->json(['success' => true, 'data' => $accounts]);
    }
}
