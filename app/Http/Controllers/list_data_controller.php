<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\user_data;

class list_data_controller extends Controller
{
    function show(){
        $data=  user_data::all();
             return view('view_data',['member'=>$data]);
          
    }
}
