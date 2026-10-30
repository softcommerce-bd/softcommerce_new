<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;
    protected $guarded = [];

    function department(){
        return $this->belongsTo(CareerDepartment::class,'department_id','id');
    }
    function job(){
        return $this->belongsTo(CareerJobPost::class,'job_id','id');
    }
}
