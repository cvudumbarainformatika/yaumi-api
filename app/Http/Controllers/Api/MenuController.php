<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Menu;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
       $menus = Menu::whereNull('parent_id')
        ->with('children') // bisa di-nested lebih dalam jika perlu
        ->orderBy('order')
        ->get();

      $flatten = Menu::all()->toArray();
      $data = [
        'flatten' => $flatten,
        'menus' => $menus
      ];
      return response()->json($data );
    }

    
}
