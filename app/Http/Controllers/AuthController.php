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

use Illuminate\Http\Request;

class AuthController extends Controller
{
    use HttpResponse;

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
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            // Jika validasi gagal, kembalikan error dengan format JSON
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        // 2. Handle file upload (sudah benar)
        $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');

        // 3. Buat user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'bio' => $request->bio,
            'profile_picture' => 'storage/' . $profilePicturePath,
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
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'experience_years' => 'required|integer|min:0',
            'portofolio_link' => 'nullable|url', // 'nullable' berarti tidak wajib
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), HttpResponseCode::HTTP_UNPROCESSABLE_ENTITY, $validator->errors());
        }

        // 2. Handle file upload
        $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');

        // 3. Buat User
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'bio' => $request->bio,
            'profile_picture' => 'storage/' . $profilePicturePath,
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
        // Validate the request data
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // No email found
        if (!$user) {
            return redirect()->back()->with('error', 'Email not found!');
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            return redirect()->back()->with('error', 'Invalid credentials!');
        }

        // Search the customer
        $customer = Customer::where('user_id', $user->id)->first();
        if (!$customer) {
            return redirect()->back()->with('error', 'Not a customer!');
        }

        // Log the user in
        session()->flush();
        Session::put('user_id', $user->id);
        Session::put('profile_picture', $user->profile_picture);
        Session::put('customer_id', $customer->id);

        // Redirect
        return redirect()->route('home')->with('success', 'Login successful!');
    }

    public function loginIllustrator(Request $request)
    {
        // Validate the request data
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // No email found
        if (!$user) {
            return redirect()->back()->with('error', 'Email not found!');
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            return redirect()->back()->with('error', 'Invalid credentials!');
        }

        // Search the illustrator
        $illustrator = Illustrator::where('user_id', $user->id)->first();
        if(!$illustrator){
            return redirect()->back()->with('error', 'Not an illustrator!');
        }

        // Log the user in
        session()->flush();
        Session::put('user_id', $user->id);
        Session::put('profile_picture', $user->profile_picture);
        Session::put('illustrator_id', $illustrator->id);

        // Redirect
        return redirect()->route('home')->with('success', 'Login successful!');
    }

    public function logout(){
        session()->flush();
        return redirect()->route('index')->with('success', 'Logged out!');
    }
}
