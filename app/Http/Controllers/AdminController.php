<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Illustration;
use App\Models\Illustrator;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Utils\HttpResponse;
use App\Utils\HttpResponseCode; 
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class AdminController extends Controller
{
    use HttpResponse;

    public function index()
    {
        return view('admin.layouts.main');
    }

    public function showLogin()
    {
        return view('admin.login');
    }

    // public function redirect()
    // {
    //     return Socialite::driver('google')->redirect();
    // }

    public function checkEmail(Request $request)
    {
        // 1. Validasi input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 2. Cari admin berdasarkan email
        $admin = Admin::where('email', $request->email)->first();

        // 3. Berikan respons
        if ($admin) {
            // Jika ditemukan, kirim respons sukses
            return $this->success('Admin verified', $admin);
        } else {
            // Jika tidak ditemukan, kirim respons error
            return $this->error('Unauthorized email', HttpResponseCode::HTTP_UNAUTHORIZED);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success('Token revoked successfully.');
    }

    public function showCustomers()
    {
        try {
            // Logika query sama persis seperti di fungsi lama Anda
            $customers = Customer::with('user')->get();
            
            // Kirim data sebagai respons JSON yang sukses
            return $this->success('Customers retrieved successfully', $customers);

        } catch (\Exception $e) {
            // Penanganan error jika query gagal
            return $this->error('Failed to retrieve customers.', 500);
        }
    }
    public function showIllustrators()
    {
        try {
            // Logika query sama persis dengan fungsi lama
            $illustrators = Illustrator::with('user')->get();
            
            // Kirim data sebagai respons JSON yang sukses
            return $this->success('Illustrators retrieved successfully', $illustrators);

        } catch (\Exception $e) {
            // Penanganan error jika query gagal
            return $this->error('Failed to retrieve illustrators.', 500);
        }
    }

    public function deleteUser($id)
    {
        try {
            $user = User::find($id);

            // Jika user tidak ditemukan, kirim error 404 Not Found
            if (!$user) {
                return $this->error('User not found.', HttpResponseCode::HTTP_NOT_FOUND);
            }

            // Tambahan: Anda mungkin ingin menambahkan logika otorisasi di sini
            // Misalnya, jangan biarkan admin menghapus dirinya sendiri atau admin lain yang levelnya lebih tinggi.
            
            $user->delete();

            // Kirim respons sukses
            return $this->success('User deleted successfully.');

        } catch (\Exception $e) {
            return $this->error('Failed to delete user.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function showEditCustomer($id)
    {
        try {
            // Gunakan find() untuk pencarian via primary key, ini lebih ringkas
            // with('user') tetap digunakan untuk mengambil data relasi
            $customer = Customer::with('user')->find($id);

            // Jika customer dengan ID tersebut tidak ditemukan
            if (!$customer) {
                return $this->error('Customer not found.', HttpResponseCode::HTTP_NOT_FOUND);
            }

            // Jika ditemukan, kirim data sebagai respons JSON
            return $this->success('Customer retrieved successfully', $customer);

        } catch (\Exception $e) {
            return $this->error('Failed to retrieve customer data.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function showEditIllustrator($id)
    {
        try {
            // Gunakan find() untuk pencarian via primary key
            $illustrator = Illustrator::with('user')->find($id);

            // Jika illustrator dengan ID tersebut tidak ditemukan
            if (!$illustrator) {
                return $this->error('Illustrator not found.', HttpResponseCode::HTTP_NOT_FOUND);
            }

            // Jika ditemukan, kirim data sebagai respons JSON
            return $this->success('Illustrator retrieved successfully', $illustrator);

        } catch (\Exception $e) {
            return $this->error('Failed to retrieve illustrator data.', 500, ['error' => $e->getMessage()]);
        }
    }

    public function editCustomer(Request $request, $id)
    {
        // 1. Temukan customer terlebih dahulu
        $customer = Customer::find($id);
        if (!$customer) {
            return $this->error('Customer not found.', HttpResponseCode::HTTP_NOT_FOUND);
        }

        // 2. Lakukan validasi data yang masuk dari frontend
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // Pastikan email unik, tapi abaikan email milik user ini sendiri
            'email' => ['required', 'email', Rule::unique('users')->ignore($customer->user_id)],
            'bio' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 3. Lakukan update pada model User yang berelasi
        try {
            $customer->user->update($validator->validated());
            return $this->success('Customer updated successfully', $customer->load('user'));
        } catch (\Exception $e) {
            return $this->error('Failed to update customer data.', 500);
        }
    }

    public function editIllustrator(Request $request, $id)
    {
        $illustrator = Illustrator::find($id);
        if (!$illustrator) {
            return $this->error('Illustrator not found.', HttpResponseCode::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($illustrator->user_id)],
            'bio' => 'nullable|string',
            'experience_years' => 'required|integer|min:0',
            'portofolio_link' => 'nullable|url',
            'is_open_commision' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Gunakan Transaksi Database untuk menjaga konsistensi data
        try {
            DB::transaction(function () use ($illustrator, $validator) {
                $validatedData = $validator->validated();
                
                // 1. Update data pada model User yang berelasi
                $illustrator->user->update([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'bio' => $validatedData['bio'] ?? $illustrator->user->bio,
                ]);

                // 2. Update data pada model Illustrator
                $illustrator->update([
                    'experience_years' => $validatedData['experience_years'],
                    'portofolio_link' => $validatedData['portofolio_link'] ?? $illustrator->portofolio_link,
                    'is_open_commision' => $validatedData['is_open_commision'],
                ]);
            });

            // Ambil data terbaru setelah diupdate untuk dikembalikan
            return $this->success('Illustrator updated successfully', $illustrator->fresh()->load('user'));

        } catch (\Exception $e) {
            Log::error('Illustrator update failed: ' . $e->getMessage());
            return $this->error('Failed to update illustrator due to a server error.', 500);
        }
    }

    public function showPurchases()
    {
        try {
            // Pindahkan query kompleks dari frontend ke sini
            $purchases = Purchase::with(['customer.user', 'illustration'])
                ->whereHas('illustration', function ($query) {
                    $query->where('is_sold', 1);
                })
                ->latest() // Tambahan: urutkan berdasarkan yang terbaru
                ->get();

            return $this->success('Sold purchases retrieved successfully', $purchases);

        } catch (\Exception $e) {
            Log::error('Purchase retrieval failed: ' . $e->getMessage());
            return $this->error('Failed to retrieve purchase data.', 500);
        }
    }

    public function verify($id)
    {
        $purchase = Purchase::with('illustration')->find($id);

        if (!$purchase) {
            return $this->error('Purchase not found.', HttpResponseCode::HTTP_NOT_FOUND);
        }

        // Gunakan transaksi untuk memastikan kedua update berhasil atau keduanya gagal.
        try {
            DB::transaction(function () use ($purchase) {
                // 1. Verifikasi pembelian
                $purchase->is_verified = 1;
                $purchase->save();

                // 2. Update status ilustrasi menjadi "terkirim" atau "selesai" (asumsi status 2)
                $purchase->illustration->is_sold = 2;
                $purchase->illustration->save();
            });

            return $this->success('Purchase verified successfully.');

        } catch (\Exception $e) {
            Log::error("Purchase verification failed for ID {$id}: " . $e->getMessage());
            return $this->error('Purchase verification failed due to a server error.', 500);
        }
    }

    public function reject($id)
    {
        $purchase = Purchase::with('illustration')->find($id);

        if (!$purchase) {
            return $this->error('Purchase not found.', HttpResponseCode::HTTP_NOT_FOUND);
        }

        // Gunakan transaksi untuk memastikan kedua update berhasil atau keduanya gagal.
        try {
            DB::transaction(function () use ($purchase) {
                // 1. Set status verifikasi menjadi 0 (ditolak/belum diverifikasi)
                $purchase->is_verified = 0;
                $purchase->save();

                // 2. Kembalikan status ilustrasi menjadi tersedia (is_sold = 0)
                $purchase->illustration->is_sold = 0;
                $purchase->illustration->save();
            });

            return $this->success('Purchase rejected successfully.');

        } catch (\Exception $e) {
            Log::error("Purchase rejection failed for ID {$id}: " . $e->getMessage());
            return $this->error('Purchase rejection failed due to a server error.', 500);
        }
    }
}
