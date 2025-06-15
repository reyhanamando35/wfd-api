<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Illustrator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use App\Utils\HttpResponse; 
use Illuminate\Support\Facades\Validator;
use App\Utils\HttpResponseCode; 
use Illuminate\Support\Facades\Auth;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    use HttpResponse;

    public function __construct()
    {
        
    }

    public function showRegisterCustomer()
    {
        return view('auth.register.customer');
    }

    public function showRegisterIllustrator()
    {
        return view('auth.register.illustrator');
    }

    public function showLoginCustomer()
    {
        return view('auth.login.customer');
    }

    public function showLoginIllustrator()
    {
        return view('auth.login.illustrator');
    }

    public function registerCustomer(Request $request)
    {
        // 1. Validasi data secara manual
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'bio' => 'required|string|max:500',
            'profile_picture' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            // Jika validasi gagal, kembalikan error dengan format JSON
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }
        
        // 2. Handle file upload (sudah benar)
        // $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');

        // 3. Buat user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'bio' => $request->bio,
            'profile_picture' => $request->profile_picture,
        ]);

        // 4. Buat customer
        Customer::create([
            'user_id' => $user->id,
        ]);

        // 5. Kembalikan response sukses dengan format JSON
        return $this->success('Account created successfully!', $user, HttpResponseCode::HTTP_CREATED);
    }

    public function registerIllustrator(Request $request)
    {
        // 1. Validasi data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'bio' => 'required|string|max:500',
            'profile_picture' => 'required|string|max:255',
            'experience_years' => 'required|integer|min:0',
            'portofolio_link' => 'nullable|url', // 'nullable' berarti tidak wajib
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        // 2. Handle file upload
        // $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');

        // 3. Buat User
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'bio' => $request->bio,
            'profile_picture' => $request->profile_picture,
        ]);

        // 4. Buat Illustrator
        $illustrator = Illustrator::create([
            'user_id' => $user->id,
            'experience_years' => $request->experience_years,
            'portofolio_link' => $request->portofolio_link,
            'is_open_commision' => $request->boolean('is_open_commision'), // Cara aman untuk handle boolean
        ]);

        // 5. Kembalikan response sukses
        return $this->success(
            'Illustrator account created successfully!',
            [
                'user' => $user,
                'illustrator_details' => $illustrator
            ],
            HttpResponseCode::HTTP_CREATED
        );
    }

    public function loginCustomer(Request $request)
    {
        // 1. Validasi
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 2. Coba otentikasi menggunakan Auth::attempt
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->error('Invalid credentials!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        // 3. Dapatkan user yang sudah terotentikasi
        $user = User::where('email', $request->email)->first();

        // 4. Pastikan user ini adalah seorang customer
        if (!$user->customer()->exists()) {
             // Jika bukan customer, logout dan beri error
             Auth::logout();
             return $this->error('Your account is not a customer account.', HttpResponseCode::HTTP_FORBIDDEN);
        }

        // 5. Buat API token untuk user
        $token = $user->createToken('auth_token_customer')->plainTextToken;

        // 6. Kembalikan response sukses beserta token dan data user
        return $this->success(
            'Login successful!',
            [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'customer_id' => $user->customer->id
            ]
        );
    }

    public function loginIllustrator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY);
        }

        // 2. Coba otentikasi menggunakan Auth::attempt
        if (!Auth::attempt($request->only('email', 'password'))) {
            return $this->error('Invalid credentials!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        // 3. Dapatkan user yang sudah terotentikasi
        $user = User::where('email', $request->email)->first();

        // 4. Pastikan user ini adalah seorang illustrator
        if (!$user->illustrator()->exists()) {
             // Jika bukan illustrator, logout dan beri error
             Auth::logout();
             return $this->error('Your account is not a Illustrator account.', HttpResponseCode::HTTP_FORBIDDEN);
        }

        // 5. Buat API token untuk user
        $token = $user->createToken('auth_token_illustrator')->plainTextToken;

        // 6. Kembalikan response sukses beserta token dan data user
        return $this->success(
            'Login successful!',
            [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => $user,
                'illustrator_id' => $user->illustrator->id
            ]
        );
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // 2. LAKUKAN PENGECEKAN! Pastikan user benar-benar ada.
        if ($user) {
            // Jika user ada, hapus semua token miliknya.
            // Cara ini lebih efisien daripada menggunakan ->each()
            $user->tokens()->delete();
            
            // Kembalikan response sukses
            return $this->success('Logout Successfully');
        }

        // 3. Jika tidak ada user (token tidak valid), kembalikan error Unauthorized.
        // Ini mencegah server dari crash dan memberikan respons yang benar.
        return $this->error('User not authenticated.', 401);
    }
}
