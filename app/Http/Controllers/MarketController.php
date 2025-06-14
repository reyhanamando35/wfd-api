<?php

namespace App\Http\Controllers;

use App\Models\Category;

use App\Models\Illustration;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class MarketController extends Controller
{
    public function index()
    {
        $illustrations = Illustration::all();
        $categories = Category::all();
        return view('market', compact('illustrations', 'categories'));
    }

    public function showIllustration($id)
    {
        $art = Illustration::findOrFail($id);
        return view('illustrations.detail', compact('art'));
    }

    public function showSell()
    {
        $categories = Category::all();
        return view('illustrations.sell', compact('categories'));
    }

    public function showBuy($id)
    {
        $art = Illustration::findOrFail($id);
        return view('illustrations.buy', compact('art'));
    }

    public function sell(Request $request)
    {
        // Validate the incoming data
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:1',
            'date_issued' => 'required|date',
            'category_id' => 'required|exists:categories,id',
            'image_path' => 'required|image|mimes:jpeg,png,jpg|max:4096',
        ]);

        // Handle file upload
        if ($request->hasFile('image_path')) {
            $imagePath = $request->file('image_path')->store('uploads', 'public');
            $validated['image_path'] = $imagePath;
        }

        // Save to database
        Illustration::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'date_issued' => $validated['date_issued'],
            'category_id' => $validated['category_id'],
            'image_path' => 'storage/' . $validated['image_path'],
            'illustrator_id' => Session::get('illustrator_id'),
        ]);

        return redirect()->route('market')->with('success', 'Artwork successfully listed!');
    }

    public function buy(Request $request)
    {
        // Validate the incoming data
        $validated = $request->validate([
            'id' => 'required|integer|exists:illustrations,id',
            'payment_method' => 'required',
            'file_path' => 'required|image|mimes:jpeg,png,jpg|max:4096',
        ]);

        $art = Illustration::findOrFail($validated['id']);
        if ($art->is_sold == 1) {
            return redirect()->back()->with('error', 'Artwork waiting for approval!');
        } else if ($art->is_sold == 2) {
            return redirect()->back()->with('error', 'Artwork already sold!');
        }

        // Handle file upload
        if ($request->hasFile('file_path')) {
            $imagePath = $request->file('file_path')->store('uploads', 'public');
            $validated['file_path'] = $imagePath;
        }

        // Save to database
        Purchase::create([
            'payment_method' => $validated['payment_method'],
            'file_path' => 'storage/' . $validated['file_path'],
            'illustration_id' => $validated['id'],
            'customer_id' => Session::get('customer_id'),
        ]);

        // make status to pending
        $art->is_sold = 1;
        $art->save();

        return redirect()->route('market')->with('success', 'Artwork successfully bought!');
    }

    public function filter(Request $request)
    {
        $title = $request->get('title', '');
        $minPrice = $request->get('minPrice', '');
        $maxPrice = $request->get('maxPrice', '');
        $category = $request->get('category', '');

        $illustrations = Illustration::with(['illustrator.user']) 
            ->when(!empty($title), function ($query) use ($title) {
                $query->where('title', 'like', '%' . $title . '%');
            })
            ->when(!empty($minPrice), function ($query) use ($minPrice) {
                $query->where('price', '>=', $minPrice);
            })
            ->when(!empty($maxPrice), function ($query) use ($maxPrice) {
                $query->where('price', '<=', $maxPrice);
            })
            ->when(!empty($category), function ($query) use ($category) {
                $query->where('category_id', $category);
            })
            ->get();

        // Map the results to include all required fields
        $illustrations = $illustrations->map(function ($illustration) {
            return [
                'id' => $illustration->id,
                'image_path' => $illustration->image_path,
                'title' => $illustration->title,
                'price' => $illustration->price,
                'illustrator_name' => $illustration->illustrator->user->name ?? 'N/A', // Safely access user name
            ];
        });

        // Return the filtered data as JSON
        return response()->json($illustrations);
    }
}
