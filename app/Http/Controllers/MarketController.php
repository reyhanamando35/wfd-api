<?php

namespace App\Http\Controllers;

use App\Models\Category;

use App\Models\Illustration;
use App\Models\Purchase;
use Illuminate\Http\Request;
use App\Utils\HttpResponse; 
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Validator;
use App\Utils\HttpResponseCode; 
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;

class MarketController extends Controller
{
    public function __construct()
    {
        
    }
    public function getIllustrationsForMarket()
    {
        // Ambil data dari model seperti biasa
        $illustrations = Illustration::with('illustrator.user')->latest()->get(); // Contoh dengan relasi
        $categories = Category::all();

        // Gabungkan data dalam satu array
        $data = [
            'illustrations' => $illustrations,
            'categories' => $categories,
        ];

        // Kembalikan sebagai respons JSON sukses menggunakan trait Anda
        return $this->success('Data untuk pasar berhasil diambil', $data);
    }

    public function getCategoriesApi()
    {
        try {
            // Ambil semua data dari model Category
            $categories = Category::all();
            
            // Kirim response sukses menggunakan HttpResponse trait
            return $this->success('Categories retrieved successfully', $categories);

        } catch (\Exception $e) {
            // Kirim response error jika terjadi masalah
            return $this->error('Failed to retrieve categories', 500, $e->getMessage());
        }
    }

    public function showIllustrationsApi($id)
    {
        try {
            // INTI PERBAIKAN: Menggunakan relasi yang benar 'illustrator.user' dan 'category'
            $illustration = Illustration::with(['illustrator.user', 'category'])->findOrFail($id);

            // Kirim response sukses
            return $this->success('Illustration retrieved successfully', $illustration);

        } catch (ModelNotFoundException $e) {
            // Jika findOrFail gagal, kirim response error 404 Not Found
            return $this->error('Illustration not found', HttpResponseCode::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            // Handle error lainnya yang mungkin terjadi
            Log::error('Show Illustration API Error for ID ' . $id . ': ' . $e->getMessage());
            return $this->error('An error occurred while retrieving data.', HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sell(Request $request)
    {
        // PERUBAHAN 1: Validasi 'image_path' sekarang adalah string, bukan file.
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:1',
            'date_issued' => 'required|date',
            'category_id' => 'required|exists:categories,id',
            'image_path' => 'required|string|max:255', // DIUBAH
        ]);

        $user = $request->user();

        // PERUBAHAN 2: Logika upload file dihapus dari backend.

        // PERUBAHAN 3: 'image_path' langsung diambil dari request.
        $illustration = Illustration::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'date_issued' => $validated['date_issued'],
            'category_id' => $validated['category_id'],
            'image_path' => $validated['image_path'], // DIUBAH
            'illustrator_id' => $user->illustrator->id,
        ]);

        return $this->success('Artwork successfully listed!', $illustration, HttpResponseCode::HTTP_CREATED);
    }

    public function buy(Request $request)
    {
        // PERUBAHAN 1: Validasi 'file_path' (bukti bayar) sekarang adalah string.
        $validator = Validator::make($request->all(), [
            'illustration_id' => 'required|integer|exists:illustrations,id',
            'payment_method' => 'required|string',
            'file_path' => 'required|string|max:255', // DIUBAH
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        $art = Illustration::findOrFail($request->illustration_id);
        if ($art->is_sold == 1) {
            return $this->error('Artwork is waiting for approval!', HttpResponseCode::HTTP_CONFLICT);
        } else if ($art->is_sold == 2) {
            return $this->error('Artwork is already sold!', HttpResponseCode::HTTP_CONFLICT);
        }

        $user = $request->user();
        if (!$user) {
            return $this->error('Unauthenticated.', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        // $user->load('customer');
        if (!$user->customer) {
            return $this->error('User is not a valid customer.', HttpResponseCode::HTTP_FORBIDDEN);
        }
        
        $customerId = $user->customer->id;

        // PERUBAHAN 2: Logika upload file dihapus dari backend.

        // PERUBAHAN 3: 'file_path' langsung diambil dari request.
        $purchase = Purchase::create([
            'payment_method' => $request->payment_method,
            'file_path' => $request->file_path, // DIUBAH
            'illustration_id' => $request->illustration_id,
            'customer_id' => $customerId,
        ]);

        $art->is_sold = 1; // Pending
        $art->save();

        return $this->success('Purchase request sent successfully, waiting for approval.', $purchase, HttpResponseCode::HTTP_CREATED);
    }
    public function filter(Request $request)
    {
        try {
            // Mulai dengan query dasar
            $query = Illustration::query()->with('illustrator.user');

            // Terapkan filter secara kondisional
            if ($request->filled('title')) {
                $query->where('title', 'like', '%' . $request->title . '%');
            }

            if ($request->filled('minPrice')) {
                $query->where('price', '>=', $request->minPrice);
            }

            if ($request->filled('maxPrice')) {
                $query->where('price', '<=', $request->maxPrice);
            }

            if ($request->filled('category')) {
                $query->where('category_id', $request->category);
            }

            // Ambil hasilnya setelah semua filter diterapkan
            $illustrations = $query->where('is_sold', 0)->latest()->get();

            // Kembalikan sebagai JSON, jangan lupa toArray() agar relasi ikut
            return $this->success('Illustrations filtered successfully', $illustrations->toArray());

        } catch (\Exception $e) {
            Log::error('Filter API Error: ' . $e->getMessage());
            return $this->error('Failed to filter illustrations.', 500);
        }
    }
}
