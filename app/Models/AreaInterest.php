<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AreaInterest extends Model
{
    protected $fillable = [
        'pincode',
        'locality',
        'district',
        'state',
        'phone',
        'email',
    ];
}
