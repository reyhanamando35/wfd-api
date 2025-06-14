<?php

namespace App\Http\Controllers;

use App\Models\Illustration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $arts = Illustration::limit(8)->get();
        return view('dashboard', compact('arts'));
    }

    public function showListings()
    {
        $arts = Illustration::where('illustrator_id', Session::get('illustrator_id'))->get();
        return view('listings', compact('arts'));
    }

    public function showCollections()
    {
        $arts = DB::table('illustrations')
            ->join('purchases', 'illustrations.id', '=', 'purchases.illustration_id')
            ->join('customers', 'purchases.customer_id', '=', 'customers.id')
            ->where('purchases.customer_id', Session::get('customer_id'))
            ->select('illustrations.*', 'purchases.*', 'customers.*')
            ->get();
        return view('collections', compact('arts'));
    }

    public function showHistories()
    {
        $arts = DB::table('illustrations')
            ->join('purchases', 'illustrations.id', '=', 'purchases.illustration_id')
            ->join('customers', 'purchases.customer_id', '=', 'customers.id')
            ->where('purchases.customer_id', Session::get('customer_id'))
            ->select('illustrations.*', 'purchases.*', 'customers.*')
            ->get();

        return view('histories', compact('arts'));
    }

    public function showProfile($id)
    {
        $user = User::findOrFail($id);

        $artCount = -1;
        if($user->illustrator)
        {
            $arts = Illustration::where('illustrator_id', $user->illustrator->id)->get();
            $artCount = $arts->count();
        }

        $openCommision = 0;
        if($user->illustrator && $user->illustrator->is_open_commision)
        {
            $openCommision = 1;
        }

        return view('profile', compact('user', 'artCount', 'openCommision'));
    }
}
