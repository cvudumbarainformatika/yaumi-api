<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use LogsActivity;
    protected $table = 'menus';

    protected $fillable = ['name', 'route', 'parent_id', 'order'];

    public function cashFlows()
    {
        return $this->hasMany(CashFlow::class);
    }

    // Relasi ke parent
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    // Relasi ke children
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }
}
