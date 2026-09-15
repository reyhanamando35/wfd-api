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
            'profile_picture' => self::ASSET_PATH_RULE,
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
            'profile_picture' => self::ASSET_PATH_RULE,
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

        // 2. Cek kredensial tanpa login ke guard session: API ini stateless dan hanya memakai token Sanctum
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        // 4. Pastikan user ini adalah seorang customer
        if (!$user->customer()->exists()) {
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

        // 2. Cek kredensial tanpa login ke guard session: API ini stateless dan hanya memakai token Sanctum
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->error('Invalid credentials!', HttpResponseCode::HTTP_UNAUTHORIZED);
        }

        // 4. Pastikan user ini adalah seorang illustrator
        if (!$user->illustrator()->exists()) {
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

    public function submitEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        // Respons sama untuk email terdaftar maupun tidak, supaya form ini tidak bisa dipakai mengecek email siapa yang punya akun
        $sentMessage = 'If the email is registered, a password reset link has been sent. Please check your email.';
        if (!User::where('email', $request->email)->exists()) {
            return $this->success($sentMessage);
        }

        $token = \Illuminate\Support\Str::random(64);

        // Simpan hash token supaya token asli hanya ada di email
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            ['token' => hash('sha256', $token), 'created_at' => now()]
        );

        $resetUrl = rtrim(env('FRONTEND_URL', 'http://localhost:8000'), '/') . '/validasi-forgot-password/' . $token;

        \Illuminate\Support\Facades\Mail::raw(
            "You requested a password reset for your Illustrasia account.\n\n"
            . "Click the link below to reset your password (valid for 60 minutes):\n"
            . $resetUrl . "\n\n"
            . "If you did not request this, you can ignore this email.",
            function ($message) use ($request) {
                $message->to($request->email)->subject('Illustrasia - Reset Password');
            }
        );

        return $this->success($sentMessage);
    }

    public function validasiPW(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('token', hash('sha256', $request->token))
            ->first();

        if (!$record || \Carbon\Carbon::parse($record->created_at)->diffInMinutes(now()) > 60) {
            return $this->error('Token is not valid', HttpResponseCode::HTTP_BAD_REQUEST);
        }

        return $this->success('Token is valid');
    }

    public function validasiPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8',
            'confirmPassword' => 'required|same:password',
            'token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('token', hash('sha256', $request->token))
            ->first();

        if (!$record || \Carbon\Carbon::parse($record->created_at)->diffInMinutes(now()) > 60) {
            return $this->error('Token is not valid', HttpResponseCode::HTTP_BAD_REQUEST);
        }

        User::where('email', $record->email)->update([
            'password' => Hash::make($request->password),
        ]);

        // Token sekali pakai
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $record->email)->delete();

        return $this->success('Password has been reset');
    }
}
