<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Illustrator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

use Illuminate\Http\Request;

class AuthController extends Controller
{
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
        // Validate the request data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'bio' => 'required|string|max:500',
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Handle file upload
        $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'bio' => $request->bio,
            'profile_picture' => 'storage/' . $profilePicturePath,
        ]);

        // Create customer
        $customer = Customer::create([
            'user_id' => $user->id,
        ]);

        // Set redirect route
        return redirect()->route('login.customer')->with('success', 'Account created!');
    }

    public function registerIllustrator(Request $request)
    {
        // Validate the request data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'bio' => 'required|string|max:500',
            'profile_picture' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'experience_years' => 'required|integer',
        ]);

        // Handle file upload
        $profilePicturePath = $request->file('profile_picture')->store('profile_pictures', 'public');

        // Create the user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'bio' => $request->bio,
            'profile_picture' => 'storage/' . $profilePicturePath,
        ]);

        // Create illustrator
        $illustrator = Illustrator::create([
            'user_id' => $user->id,
            'experience_years' => $request['experience_years'],
            'portofolio_link' => $request['portofolio_link'] ? $request['portofolio_link'] : null,
            'is_open_commision' => $request['is_open_commision'] ? 1 : 0,
        ]);

        // Set redirect route
        return redirect()->route('login.illustrator')->with('success', 'Account created!');
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
