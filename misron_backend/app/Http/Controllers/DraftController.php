<?php

namespace App\Http\Controllers;

use App\Models\Draft;
use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Product;


class DraftController extends Controller
{
    protected $draft;
    protected $product;

    public function __construct(Draft $draft ,Product $product)
    {
        $this->draft = $draft;
        $this->product = $product;

        $this->middleware(['permission:view_drafts'])->only(['index', 'show']);
        $this->middleware(['permission:manage_drafts'])->only(['delete', 'createOrUpdate']);
    }


    public function index():View
    {
        $drafts = $this->draft->latest()->paginate(24);
        return view('backend.draft.index', compact('drafts'));
    }

    // public function show($id):View
    // {
    //     $draft = $this->draft->find($id);
    //     $ids = json_decode($draft->products_id);
    //     $products = $this->product->whereIn( 'id', $ids )->get();
       
    //     return view('backend.draft.show', compact( 'draft','products' ));
    // }
    public function show($id)
{
    $draft = Draft::findOrFail($id);
    $productsData = json_decode($draft->products_id, true);
    
    $products = [];
    if(isset($productsData['data']) && is_array($productsData['data'])) {
        foreach($productsData['data'] as $seller) {
            if(isset($seller['cart_items']) && is_array($seller['cart_items'])) {
                foreach($seller['cart_items'] as $item) {
                    $products[] = (object)[
                        'name' => $item['product_name'],
                        'unit_price' => $item['price'],
                        'quantity' => $item['quantity']
                    ];
                }
            }
        }
    }
    
    return view('backend.draft.show', compact('draft', 'products'));
}

    public function createOrUpdate(Request $request):void
    {
        // dd($request->all());
        $sessionId = session()->getId();

        $findDraft = $this->draft->where('session_id', $sessionId)->first();
        if($findDraft){
            $this->draftCreateOrUpdate( $findDraft, $request, $sessionId );
        }else{
            $draft = new Draft();
            $this->draftCreateOrUpdate( $draft, $request, $sessionId );
        }

    }

    protected function draftCreateOrUpdate( $draft, $request, $sessionId ):void
    {
        $draft->session_id = $sessionId;
        $draft->name = $request->name;
        $draft->phone = $request->phone;
        $draft->products_id = $request->products;
        $draft->address = $request->address;
        $draft->save();
    }

    public function delete($id)
    {
        $draft = $this->draft->find($id);
        $draft->delete();

        flash(translate('Draft has been inserted successfully'))->success();
        return redirect()->back();
    }
    
}
