<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareerDepartment extends Model
{
    use HasFactory;
    protected $guarded = [];

    function jobposts(){
        return $this->hasMany(CareerJobPost::class,'department_id','id');
    }
}
