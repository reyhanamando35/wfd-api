<?php

namespace App\Http\Controllers;

use App\Models\Illustration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;


class DashboardController extends Controller
{
    public function index()
    {
        $arts = Illustration::limit(8)->get();
        return view('dashboard', compact('arts'));
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
            ->select('illustrations.*') // Pilih kolom yang relevan saja untuk frontend
            ->get();

        // Kembalikan sebagai response JSON yang sukses
        return $this->success('Koleksi berhasil diambil', $arts);
    }

    public function showHistoriesApi(Request $request)
    {
        // Dapatkan pengguna yang sedang login berdasarkan token Sanctum
        $user = $request->user();

        // Asumsi ada relasi 'customer' di model User
        if (!$user->customer) {
            return $this->error('Pengguna ini bukan customer.', 404);
        }

        $customerId = $user->customer->id;

        $arts = DB::table('illustrations')
            ->join('purchases', 'illustrations.id', '=', 'purchases.illustration_id')
            ->where('purchases.customer_id', $customerId)
            ->select('illustrations.title', 'illustrations.image', 'purchases.price', 'purchases.created_at as purchase_date') // Pilih kolom yang relevan
            ->get();

        return $this->success('Riwayat pembelian berhasil diambil', $arts);
    }
   public function showProfile($id)
    {
        // Cari user, atau gagal dengan respons 404 jika tidak ditemukan
        $user = User::with('illustrator', 'customer')->find($id);

        if (!$user) {
            return $this->error('User not found', HttpResponseCode::HTTP_NOT_FOUND);
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
