<?php

namespace App\Http\Controllers;

use App\Models\Category;

use App\Models\Illustration;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;


class MarketController extends Controller
{
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

    public function showIllustrationApi($id)
    {
        // Cari data ilustrasi, atau gagal dengan exception jika tidak ditemukan
        $illustration = Illustration::with('illustrator.user')->find($id); // Muat relasi jika perlu

        if (!$illustration) {
            // Jika tidak ditemukan, kirim response error 404
            return $this->error('Illustration not found.', HttpResponseCode::HTTP_NOT_FOUND);
        }

        // Jika ditemukan, kirim response sukses dengan data ilustrasi
        return $this->success('Illustration data retrieved successfully.', $illustration);
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
            // Gunakan findOrFail untuk otomatis handle jika ID tidak ditemukan.
            // Sebaiknya muat juga relasi yang mungkin dibutuhkan di frontend (misal: data ilustrator/user)
            $illustration = Illustration::with(['user', 'category'])->findOrFail($id);

            // Kirim response sukses
            return $this->success('Illustration retrieved successfully', $illustration);

        } catch (ModelNotFoundException $e) {
            // Jika findOrFail gagal, kirim response error 404 Not Found
            return $this->error('Illustration not found', HttpResponseCode::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            // Handle error lainnya
            return $this->error('An error occurred', HttpResponseCode::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
        }
    }

    public function sell(Request $request)
    {
        // Validasi masih sama, Laravel akan otomatis mengembalikan JSON error jika gagal
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:1',
            'date_issued' => 'required|date',
            'category_id' => 'required|exists:categories,id',
            'image_path' => 'required|image|mimes:jpeg,png,jpg|max:4096',
        ]);

        // Dapatkan user (illustrator) yang sedang login melalui token Sanctum
        $user = $request->user();

        // Handle file upload
        if ($request->hasFile('image_path')) {
            // Simpan file di storage/app/public/uploads
            $imagePath = $request->file('image_path')->store('uploads', 'public');
            $validated['image_path'] = $imagePath;
        }

        // Simpan ke database
        $illustration = Illustration::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'price' => $validated['price'],
            'date_issued' => $validated['date_issued'],
            'category_id' => $validated['category_id'],
            // Dapatkan URL lengkap untuk gambar
            'image_path' => Storage::url($validated['image_path']),
            // Ambil illustrator_id dari relasi user yang terautentikasi
            'illustrator_id' => $user->illustrator->id, // Asumsi relasi 'illustrator' ada di model User
        ]);

        // Kembalikan response sukses dalam format JSON
        return $this->success('Artwork successfully listed!', $illustration, HttpResponseCode::HTTP_CREATED);
    }


    public function buy(Request $request)
    {
        // 1. Validasi data yang masuk dari API
        $validator = Validator::make($request->all(), [
            'illustration_id' => 'required|integer|exists:illustrations,id',
            'payment_method' => 'required|string',
            'file_path' => 'required|image|mimes:jpeg,png,jpg|max:4096', // Bukti pembayaran
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 2. Cek status ilustrasi
        $art = Illustration::findOrFail($request->illustration_id);
        if ($art->is_sold == 1) {
            return $this->error('Artwork is waiting for approval!', HttpResponseCode::HTTP_CONFLICT);
        } else if ($art->is_sold == 2) {
            return $this->error('Artwork is already sold!', HttpResponseCode::HTTP_CONFLICT);
        }

        // 3. Dapatkan Customer ID dari user yang terotentikasi
        $user = $request->user();
        if (!$user || !$user->customer) {
            return $this->error('User is not a valid customer.', HttpResponseCode::HTTP_FORBIDDEN);
        }
        $customerId = $user->customer->id;

        // 4. Handle file upload
        $imagePath = $request->file('file_path')->store('uploads/proofs', 'public');

        // 5. Simpan ke database
        $purchase = Purchase::create([
            'payment_method' => $request->payment_method,
            'file_path' => 'storage/' . $imagePath,
            'illustration_id' => $request->illustration_id,
            'customer_id' => $customerId, // <-- Gunakan ID dari user terotentikasi
        ]);

        // 6. Ubah status ilustrasi
        $art->is_sold = 1; // Pending
        $art->save();

        // 7. Kembalikan respons JSON sukses
        return $this->success('Purchase request sent successfully, waiting for approval.', $purchase, HttpResponseCode::HTTP_CREATED);
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
