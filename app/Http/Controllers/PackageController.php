<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $packages = Package::where('status', Package::STATUS_ACTIVE)->get();

        return Inertia::render('Package/Index', compact('packages'));
    }
}
