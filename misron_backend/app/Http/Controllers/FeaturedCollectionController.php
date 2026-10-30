<?php

namespace App\Http\Controllers;

use App\Models\FeaturedCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeaturedCollectionController extends Controller
{
    public function index()
    {
        $featuredCollections = FeaturedCollection::latest()->paginate(10);
        return view('backend.featured_collection.index', compact('featuredCollections'));
    }

    public function create()
    {
        return view('backend.featured_collection.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'link' => 'nullable|string|max:255',
            'image' => 'nullable',
        ]);

        $data = $request->only('title', 'link', 'image');
        $data['slug'] = \Str::slug($request->title);

        FeaturedCollection::create($data);

        flash(translate('Featured Collection has been inserted successfully'))->success();
        return redirect()->route('featured_collection.index');
    }

    public function edit($id)
    {
        $featured_collection = FeaturedCollection::find($id);
        return view('backend.featured_collection.edit', compact('featured_collection'));
    }
    public function update(Request $request, $id)
    {
        $featuredCollection = FeaturedCollection::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
            'link' => 'nullable|string|max:255', // use string instead of url
            'image' => 'nullable',
        ]);

        $data = $request->only('title', 'link');
        $data['slug'] = Str::slug($request->title);
        $data['image'] = $request->image;

        $featuredCollection->update($data);

        flash(translate('Featured Collection has been updated successfully'))->success();
        return redirect()->route('featured_collection.index');
    }

    public function destroy($id)
    {
        $featuredCollection = FeaturedCollection::find($id);
        $featuredCollection->delete();

        flash(translate('Featured Collection has been Deleted successfully'))->success();
        return redirect()->route('featured_collection.index');
    }
}
