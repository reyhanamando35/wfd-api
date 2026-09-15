<?php

namespace App\Http\Controllers;

use App\Models\Illustration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Utils\HttpResponse; 
use App\Utils\HttpResponseCode; 
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function __construct()
    {
        
    }

    public function dashboardList()
    {
        try {
            // Query asli: Illustration::limit(8)->get()
            // Query yang disempurnakan:
            $arts = Illustration::with('illustrator.user')
                ->where('is_sold', 0)
                ->latest()
                    ->limit(8)
                ->get();

            return $this->success('Dashboard illustrations retrieved successfully', $arts);

        } catch (\Exception $e) {
            Log::error('Dashboard illustration retrieval failed: ' . $e->getMessage());
            return $this->error('Failed to retrieve illustration data.', 500);
        }
    }

    public function getMyListings(Request $request)
    {
        // 1. Dapatkan user yang terautentikasi dari token
        $user = $request->user();

        // 2. Periksa apakah user memiliki relasi 'illustrator'
        if (!$user->illustrator) {
            return $this->error('User is not an illustrator.', 403);
        }

        // 3. Ambil illustrator_id dari relasi
        $illustratorId = $user->illustrator->id;

        // SALAH: Jangan gunakan Session di API
        // $arts = Illustration::where('illustrator_id', Session::get('illustrator_id'))->get();

        // BENAR: Gunakan ID dari user yang terautentikasi
        $arts = Illustration::where('illustrator_id', $illustratorId)->get();

        // 4. Kembalikan data sebagai JSON sukses
        return $this->success('Successfully retrieved listings', $arts);
    }

    public function showCollectionsApi()
    {
        // Dapatkan user yang sedang login via token Sanctum
        $user = Auth::user();

        // Pastikan user memiliki relasi customer dan dapatkan ID-nya
        if (!$user || !$user->customer) {
            return $this->error('User tidak ditemukan atau bukan customer', HttpResponseCode::HTTP_UNAUTHORIZED);
        }
        $customerId = $user->customer->id;

        // Query yang sama seperti sebelumnya, tapi menggunakan ID dari user yang login
        $arts = DB::table('illustrations')
            ->join('purchases', 'illustrations.id', '=', 'purchases.illustration_id')
            ->where('purchases.customer_id', $customerId)
            ->whereIn('purchases.is_verified', [0, 1]) // ← pending (0) atau verified (1)
            ->select('illustrations.*') 
            ->get();

        // Kembalikan sebagai response JSON yang sukses
        return $this->success('Koleksi berhasil diambil', $arts);
    }

    public function showHistoriesApi(Request $request)
{
    try {
        // Dapatkan pengguna yang sedang login berdasarkan token Sanctum
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau pengguna tidak ditemukan.'
            ], 401);
        }

        // Asumsi ada relasi 'customer' di model User
        if (!$user->customer) {
            return response()->json([
                'success' => false,
                'message' => 'Pengguna ini bukan customer.'
            ], 404);
        }

        $customerId = $user->customer->id;

        // PERBAIKAN QUERY: Ambil semua data yang diperlukan
        $arts = DB::table('purchases')
            ->join('illustrations', 'purchases.illustration_id', '=', 'illustrations.id')
            ->where('purchases.customer_id', $customerId)
            ->select(
                'purchases.id as purchase_id',
                'purchases.payment_method',
                'purchases.file_path',
                'purchases.is_verified',
                'purchases.created_at as purchase_date',
                'illustrations.id as illustration_id',
                'illustrations.title',
                'illustrations.image_path',
                'illustrations.price'
            )
            ->orderBy('purchases.created_at', 'desc') // Urutkan dari terbaru
            ->get();

        // Debug: Log hasil query
        \Log::info('Histories API called, found ' . $arts->count() . ' records for customer ' . $customerId);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat pembelian berhasil diambil',
            'data' => $arts
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Show Histories API Error: ' . $e->getMessage());
        \Log::error('Trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'success' => false,
            'message' => 'Terjadi kesalahan server.'
        ], 500);
    }
}
   public function showProfile($id)
    {
        // Cari user, atau gagal dengan respons 404 jika tidak ditemukan
        $user = User::with('illustrator', 'customer')->find($id);

        if (!$user) {
            return $this->error('User not found', HttpResponseCode::HTTP_NOT_FOUND);
        }

        // Profil publik: email illustrator ditampilkan sebagai kontak, email customer tidak boleh bisa dikumpulkan lewat ID
        if (!$user->illustrator) {
            $user->makeHidden('email');
        }

        $artCount = -1;
        if ($user->illustrator) {
            $artCount = Illustration::where('illustrator_id', $user->illustrator->id)->count();
        }

        $openCommision = 0;
        if ($user->illustrator && $user->illustrator->is_open_commision) {
            $openCommision = 1;
        }

        // Gabungkan semua data ke dalam satu array
        $data = [
            'user' => $user,
            'art_count' => $artCount,
            'is_open_commision' => $openCommision,
        ];

        // Kembalikan data dalam format JSON yang sukses
        return $this->success('Profile data retrieved successfully', $data);
    }
}
