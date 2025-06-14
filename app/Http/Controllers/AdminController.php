<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Illustration;
use App\Models\Illustrator;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Exception;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Session;
use App\Models\User;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.layouts.main');
    }

    public function showLogin()
    {
        return view('admin.login');
    }

    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $user = Socialite::driver('google')->stateless()->user();

        $admin = Admin::where('email', $user->email)->first();
        if (!$admin) {
            return redirect()->route('admin.login')->with('error', 'Unauthorized email!');
        }

        Session::put('email', $user->email);
        Session::put('name', $user->name);

        return redirect()->route('admin.index')->with('success', 'Logged in!');
    }

    public function logout()
    {
        Session::flush();
        return redirect()->route('admin.login')->with('success', 'Logged out!');
    }

    public function showCustomers()
    {
        $customers = Customer::with('user')->get();
        return view('admin.customers', compact('customers'));
    }
    public function showIllustrators()
    {
        $illustrators = Illustrator::with('user')->get();
        return view('admin.illustrators', compact('illustrators'));
    }

    public function deleteUser($id)
    {
        $user = User::find($id);
        $user->delete();
        return redirect()->back()->with('success', 'User deleted!');
    }

    public function showEditCustomer($id)
    {
        $customer = Customer::with('user')->where('id', $id)->first();
        return view('admin.edit_customer', compact('customer'));
    }

    public function showEditIllustrator($id)
    {
        $illustrator = Illustrator::with('user')->where('id', $id)->first();
        return view('admin.edit_illustrator', compact('illustrator'));
    }

    public function editCustomer(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:customers,id',
            'name' => 'required|string',
            'email' => 'required|email',
            'bio' => 'required|string',
        ]);

        $cust = Customer::where('id', $request->id)->first();
        $user = User::findOrFail($cust->user->id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->bio = $request->bio;
        $user->save();

        return redirect()->route('admin.customers')->with('success', 'Customer edited successfully!');
    }

    public function editIllustrator(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:customers,id',
            'name' => 'required|string',
            'email' => 'required|email',
            'bio' => 'required|string',
            'experience_years' => 'required|integer',
            'is_open_commision' => 'required',
        ]);

        $ilus = Illustrator::findOrFail($request->id);
        $user = User::findOrFail($ilus->user->id);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->bio = $request->bio;
        $user->save();
        $ilus->experience_years = $request->experience_years;
        $ilus->portofolio_link = $request->portofolio_link;
        $ilus->is_open_commision = $request->is_open_commision;
        $ilus->save();

        return redirect()->route('admin.illustrators')->with('success', 'Illustrators edited successfully!');
    }

    public function showPurchases()
    {
        $purchases = Purchase::with(['customer.user', 'illustration'])
            ->whereHas('illustration', function ($query) {
                $query->where('is_sold', 1);
            })
            ->get();
        return view('admin.purchases', compact('purchases'));
    }

    public function verifyPurchase(Request $request)
    {
        $purchase = Purchase::findOrFail($request->id);
        $purchase->is_verified = 1;
        $purchase->save();

        $art = Illustration::findOrFail($purchase->illustration->id);
        $art->is_sold = 2;
        $art->save();

        return redirect()->route('admin.purchases')->with('success', 'Purchase verified!');
    }

    public function rejectPurchase(Request $request)
    {
        $purchase = Purchase::findOrFail($request->id);
        $purchase->is_verified = 0;
        $purchase->save();

        $art = Illustration::findOrFail($purchase->illustration->id);
        $art->is_sold = 0;
        $art->save();

        return redirect()->route('admin.purchases')->with('success', 'Purchase rejected!');
    }
}
