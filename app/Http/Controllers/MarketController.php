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
use Illuminate\Support\Facades\DB;


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
            Log::error('Categories API Error: ' . $e->getMessage());
            return $this->error('Failed to retrieve categories', 500);
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
            'image_path' => self::ASSET_PATH_RULE,
        ]);

        $user = $request->user();
        if (!$user->illustrator) {
            return $this->error('Only illustrators can list artworks.', HttpResponseCode::HTTP_FORBIDDEN);
        }

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
            'payment_method' => 'required|string|in:bca,bri,ovo',
            'file_path' => self::ASSET_PATH_RULE,
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $request->user();
        if (!$user?->customer) {
            return $this->error('User is not a valid customer.', HttpResponseCode::HTTP_FORBIDDEN);
        }

        // Kunci karya secara atomik: hanya satu request yang bisa mengubah status 0 (tersedia) -> 1 (pending).
        // Cek-lalu-simpan terpisah membuat dua customer bisa membeli karya yang sama bersamaan.
        $purchase = DB::transaction(function () use ($request, $user) {
            $locked = Illustration::where('id', $request->illustration_id)->where('is_sold', 0)->update(['is_sold' => 1]);
            if (!$locked) {
                return null;
            }

            return Purchase::create([
                'payment_method' => $request->payment_method,
                'file_path' => $request->file_path,
                'illustration_id' => $request->illustration_id,
                'customer_id' => $user->customer->id,
            ]);
        });

        if (!$purchase) {
            return $this->error('Artwork is no longer available.', HttpResponseCode::HTTP_CONFLICT);
        }

        return $this->success('Purchase request sent successfully, waiting for approval.', $purchase, HttpResponseCode::HTTP_CREATED);
    }
    public function filter(Request $request)
    {
        try {
            // Mulai dengan query dasar
            $query = Illustration::query()->with('illustrator.user');

            // Terapkan filter secara kondisional
            if ($request->filled('title')) {
                $query->whereLike('title', '%' . $request->title . '%');
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

    public function filterCollections(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->customer) {
                return $this->error('User tidak ditemukan atau bukan customer', 401);
            }

            $customerId = $user->customer->id;

            // Mulai query
            $query = DB::table('illustrations')
                ->join('purchases', 'illustrations.id', '=', 'purchases.illustration_id')
                ->where('purchases.customer_id', $customerId);

            // Filter berdasarkan status yang diminta
            $statusFilter = $request->input('status', 'all'); // default: all (pending + verified)
            
            switch($statusFilter) {
                case 'verified':
                    $query->where('purchases.is_verified', 1);
                    break;
                case 'pending':
                    $query->where('purchases.is_verified', 0);
                    break;
                case 'all':
                    // Tampilkan semua kecuali rejected
                    $query->whereIn('purchases.is_verified', [0, 1]);
                    break;
                case 'rejected':
                    $query->where('purchases.is_verified', 2);
                    break;
                default:
                    // Default: tidak tampilkan rejected
                    $query->whereIn('purchases.is_verified', [0, 1]);
            }

            // Join dengan tabel lain
            $query->join('illustrators', 'illustrations.illustrator_id', '=', 'illustrators.id')
                ->join('users', 'illustrators.user_id', '=', 'users.id')
                ->join('categories', 'illustrations.category_id', '=', 'categories.id')
                ->select(
                    'illustrations.*',
                    'users.name as illustrator_name',
                    'users.email as illustrator_email',
                    'categories.name as category_name',
                    'purchases.is_verified as purchase_status',
                    'purchases.created_at as purchased_date',
                    'purchases.payment_method'
                );

            // Filter lainnya
            if ($request->filled('title')) {
                $query->whereLike('illustrations.title', '%' . $request->title . '%');
            }

            if ($request->filled('minPrice')) {
                $query->where('illustrations.price', '>=', $request->minPrice);
            }

            if ($request->filled('maxPrice')) {
                $query->where('illustrations.price', '<=', $request->maxPrice);
            }

            if ($request->filled('category')) {
                $query->where('illustrations.category_id', $request->category);
            }

            if ($request->filled('illustrator')) {
                $query->where('illustrators.id', $request->illustrator);
            }

            // Filter berdasarkan payment method
            if ($request->filled('payment_method')) {
                $query->where('purchases.payment_method', $request->payment_method);
            }

            // Sorting
            // Nama kolom dari input tidak boleh langsung masuk orderBy
            $allowedSort = ['purchases.created_at', 'illustrations.price', 'illustrations.title', 'illustrations.date_issued'];
            $sortBy = in_array($request->sort_by, $allowedSort, true) ? $request->sort_by : 'purchases.created_at';
            $sortOrder = strtolower((string) $request->sort_order) === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Execute query
            if ($request->filled('per_page')) {
                $perPage = min($request->per_page, 100);
                $arts = $query->paginate($perPage);
                
                return $this->success('Collections filtered successfully', [
                    'data' => $arts->items(),
                    'pagination' => [
                        'total' => $arts->total(),
                        'per_page' => $arts->perPage(),
                        'current_page' => $arts->currentPage(),
                        'last_page' => $arts->lastPage(),
                    ],
                    'filters' => [
                        'status' => $statusFilter,
                        'title' => $request->title,
                        'category' => $request->category,
                        'min_price' => $request->minPrice,
                        'max_price' => $request->maxPrice,
                    ]
                ]);
            }

            $arts = $query->get();

            return $this->success('Collections filtered successfully', [
                'data' => $arts,
                'total_count' => $arts->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Filter Collections API Error: ' . $e->getMessage());
            return $this->error('Failed to filter collections.', 500);
        }
    }

    public function filterIllustrations(Request $request)
    {
        try {
            // 1. Dapatkan user yang terautentikasi dari token
            $user = $request->user();

            // 2. Periksa apakah user memiliki relasi 'illustrator'
            if (!$user->illustrator) {
                return $this->error('User is not an illustrator.', 403);
            }

            // 3. Ambil illustrator_id dari relasi
            $illustratorId = $user->illustrator->id;

            // 4. Mulai query dengan where illustrator_id
            $query = Illustration::where('illustrator_id', $illustratorId)
                                ->with(['category', 'illustrator.user']); // Eager load relationships

            // 5. Terapkan filter dari request
            if ($request->filled('title')) {
                $query->whereLike('title', '%' . $request->title . '%');
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

            // 6. Filter berdasarkan status penjualan (jika ada)
            // is_sold kolom integer 0/1/2; membandingkannya dengan boolean ditolak PostgreSQL
            if ($request->filled('is_sold') && in_array((int) $request->is_sold, [0, 1, 2], true)) {
                $query->where('is_sold', (int) $request->is_sold);
            }

            // 7. Filter berdasarkan tanggal upload
            if ($request->filled('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            if ($request->filled('date_issued_from')) {
                $query->where('date_issued', '>=', $request->date_issued_from);
            }

            if ($request->filled('date_issued_to')) {
                $query->where('date_issued', '<=', $request->date_issued_to);
            }

            // 9. Sorting options
            $sortBy = $request->filled('sort_by') ? $request->sort_by : 'created_at';
            $allowedSortColumns = ['created_at', 'updated_at', 'date_issued', 'price', 'title', 'view_count'];
            
            // Security: hanya izinkan kolom tertentu untuk sorting
            if (!in_array($sortBy, $allowedSortColumns)) {
                $sortBy = 'created_at';
            }
            
            $sortOrder = $request->filled('sort_order') ? $request->sort_order : 'desc';
            $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';
            
            $query->orderBy($sortBy, $sortOrder);

            // 10. Search in multiple fields (advanced search)
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->whereLike('title', '%' . $searchTerm . '%')
                    ->orWhereLike('description', '%' . $searchTerm . '%')
                    ->orWhereHas('category', function($categoryQuery) use ($searchTerm) {
                        $categoryQuery->whereLike('name', '%' . $searchTerm . '%');
                    });
                });
            }

            // 11. Pagination atau get all
            if ($request->filled('per_page')) {
                $perPage = min($request->per_page, 100); // Max 100 per page
                $arts = $query->paginate($perPage);
                
                // Format response dengan pagination
                return $this->success('Illustrations filtered successfully', [
                    'data' => $arts->items(),
                    'pagination' => [
                        'total' => $arts->total(),
                        'per_page' => $arts->perPage(),
                        'current_page' => $arts->currentPage(),
                        'last_page' => $arts->lastPage(),
                        'from' => $arts->firstItem(),
                        'to' => $arts->lastItem(),
                    ],
                    'filters_applied' => [
                        'title' => $request->title,
                        'category' => $request->category,
                        'min_price' => $request->minPrice,
                        'max_price' => $request->maxPrice,
                        'is_sold' => $request->is_sold,
                        'sort_by' => $sortBy,
                        'sort_order' => $sortOrder,
                    ]
                ]);
            }

            // 12. Get all jika tanpa pagination
            $arts = $query->get();

            // 13. Hitung statistik (opsional, untuk dashboard)
            $stats = [
                'total' => $arts->count(),
                // is_sold: 0 tersedia, 1 pending, 2 terjual (perbandingan "== true" dulu ikut menghitung pending)
                'sold' => $arts->where('is_sold', 2)->count(),
                'available' => $arts->where('is_sold', 0)->count(),
                'total_value' => $arts->sum('price'),
                'sold_value' => $arts->where('is_sold', 2)->sum('price'),
            ];

            return $this->success('Illustrations filtered successfully', [
                'data' => $arts,
                'stats' => $stats,
                'filters_applied' => [
                    'title' => $request->title,
                    'category' => $request->category,
                    'min_price' => $request->minPrice,
                    'max_price' => $request->maxPrice,
                    'is_sold' => $request->is_sold,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Filter Illustrations API Error: ' . $e->getMessage());
            Log::error('Trace: ' . $e->getTraceAsString());
            
            return $this->error('Failed to filter illustrations. Please try again later.', 500);
        }
    }
}
