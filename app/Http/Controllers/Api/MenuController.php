<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    public function updateBulkPermissions(Request $request)
    {
        $request->validate([
            'menus' => 'required|array',
            'menus.*.id' => 'required|integer|exists:menus,id',
            'menus.*.permission' => 'nullable|string'
        ]);

        DB::beginTransaction();

      try {
          foreach ($request->menus as $menuData) {
              $menu = Menu::find($menuData['id']);
              $menu->permission = $menuData['permission'] ?? null;
              $menu->save();
          }

          DB::commit();

          $menus = Menu::whereNull('parent_id')
          ->with('children') // bisa di-nested lebih dalam jika perlu
          ->orderBy('order')
          ->get();

          return response()->json($menus);

      } catch (\Exception $e) {
          DB::rollBack();
          return response()->json([
              'message' => 'Failed to update permissions',
              'error' => $e->getMessage()
          ], 500);
      }
    }


    
}
