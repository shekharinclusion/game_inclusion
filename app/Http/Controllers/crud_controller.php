<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\user_data;


class crud_controller extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
      
        $user_data = new user_data;
        $user_data->name = $request->name;
        $user_data->dist = $request->dist;
        $user_data->save();
        // print_r($user_data->name);
      
        return redirect('home');







        ///////
        // $alldata['formdata']=$request->all();
        // return view('test',[$alldata]);




    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = user_data::find($id);
                return view('edit', ['data' => $data]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        dd($request);die;
        $data = user_data::find($request->id);
        $data->name = $request->name;
        $data->dist = $request->dist;
        $data->save();
        // return redirect('/retrive');
        return redirect('retrive');
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {

        $data = user_data::find($id);
        $data->delete();
        return redirect('retrive');


        // $delete_item->delete();
        // return redirect('view_data');







    }
}
