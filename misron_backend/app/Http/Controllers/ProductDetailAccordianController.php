<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProductDetailAccordian;  // Ensure model is imported

class ProductDetailAccordianController extends Controller
{
    // List all accordion items
    public function index()
    {
        $accordions = ProductDetailAccordian::all();
        return view('backend.product_details_accordian.index', compact('accordions'));
    }

    // Show form to create a new accordion item
    public function create()
    {
        return view('backend.product_details_accordian.create');
    }

    // Show form to edit an existing accordion item
    public function edit($id)
    {
        $accordion = ProductDetailAccordian::findOrFail($id);  // Using findOrFail to handle errors
        return view('backend.product_details_accordian.edit', compact('accordion'));
    }

    // Store a new accordion item
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required',
        ]);

        ProductDetailAccordian::create($request->only('title', 'content'));
        flash(translate('Product Accordion Item created successfully.'))->success();
        return redirect()->route('accordions.index');
    }

    // Update an existing accordion item
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required',
        ]);

        $accordion = ProductDetailAccordian::findOrFail($id);  // Fetch the accordion item

        $accordion->update($request->only('title', 'content'));
        flash(translate('Product Accordion Item updated successfully.'))->success();
        return redirect()->route('accordions.index');
    }

    // Delete an accordion item
    public function destroy($id)
    {
        $accordion = ProductDetailAccordian::findOrFail($id);  // Fetch the accordion item
        $accordion->delete();  // Delete the item from the database

        return redirect()->route('accordions.index')->with('success', 'Accordion item deleted successfully.');
    }
}
