<?php

namespace App\Http\Controllers\Api\v2;

use App\Http\Controllers\Controller;
use App\Models\Draft;
use App\Models\Product;
use Illuminate\Http\Request;

class DraftApiController extends Controller
{
    protected $draft;
    protected $product;

    public function __construct(Draft $draft, Product $product)
    {
        $this->draft = $draft;
        $this->product = $product;
    }

    // API: Create or update draft order
    public function createOrUpdate(Request $request)
    {
        try {
            $sessionId = $request->session_id ?? session()->getId();
            
            // Validate request
            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'products' => 'nullable|string',
                'city_id' => 'nullable|integer',
                'order_note' => 'nullable|string',
                'delivery_type' => 'nullable|string',
                'pickup_point_id' => 'nullable|integer',
            ]);

            $findDraft = $this->draft->where('session_id', $sessionId)->first();
            
            if ($findDraft) {
                $this->draftCreateOrUpdate($findDraft, $validated, $sessionId);
                $draft = $findDraft;
            } else {
                $draft = new Draft();
                $this->draftCreateOrUpdate($draft, $validated, $sessionId);
            }

            return response()->json([
                'success' => true,
                'message' => 'Draft saved successfully',
                'data' => $draft
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save draft',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // API: Get draft order
    public function getDraft(Request $request)
    {
        try {
            $sessionId = $request->session_id ?? session()->getId();
            $draft = $this->draft->where('session_id', $sessionId)->first();

            if ($draft) {
                return response()->json([
                    'success' => true,
                    'data' => $draft
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No draft found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch draft'
            ], 500);
        }
    }

    // API: Delete draft order
    public function deleteDraft(Request $request)
    {
        try {
            $sessionId = $request->session_id ?? session()->getId();
            $draft = $this->draft->where('session_id', $sessionId)->first();

            if ($draft) {
                $draft->delete();
                return response()->json([
                    'success' => true,
                    'message' => 'Draft deleted successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Draft not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete draft'
            ], 500);
        }
    }

    protected function draftCreateOrUpdate($draft, $data, $sessionId): void
    {
        $draft->session_id = $sessionId;
        $draft->name = $data['name'] ?? $draft->name;
        $draft->phone = $data['phone'] ?? $draft->phone;
        $draft->email = $data['email'] ?? $draft->email;
        $draft->address = $data['address'] ?? $draft->address;
        $draft->products_id = $data['products'] ?? $draft->products_id;
        $draft->city_id = $data['city_id'] ?? null;
        $draft->order_note = $data['order_note'] ?? null;
        $draft->delivery_type = $data['delivery_type'] ?? null;
        $draft->pickup_point_id = $data['pickup_point_id'] ?? null;
        $draft->save();
    }

    // Admin: View drafts
    public function index()
    {
        $drafts = $this->draft->latest()->paginate(24);
        return view('backend.draft.index', compact('drafts'));
    }

    public function show($id)
    {
        $draft = $this->draft->find($id);
        $ids = json_decode($draft->products_id);
        $products = $this->product->whereIn('id', $ids)->get();
        return view('backend.draft.show', compact('draft', 'products'));
    }

    public function delete($id)
    {
        $draft = $this->draft->find($id);
        $draft->delete();

        flash(translate('Draft has been deleted successfully'))->success();
        return redirect()->back();
    }
}
