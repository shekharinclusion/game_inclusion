<?php

namespace App\Http\Controllers;
use App\Models\user_data;

use Illuminate\Http\Request;

class get_data extends Controller
{
    function data(){
     user_data::all();
        
      
    }
}
