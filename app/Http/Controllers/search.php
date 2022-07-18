<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use app\Models\user_data;

class search extends Controller
{
    public function show($id)
    {
        $data = user_data::find($id);
                return view('search', ['data' => $data]);
    }

    function get_data($id)
    {

        $data = user_data::find($id);
       
        return view('search', ['data' => $data]);

    }
}
