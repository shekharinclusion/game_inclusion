<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\get_data;
use App\Http\Controllers\crud_controller;
use App\Http\Controllers\usercontroller;

use App\Http\Controllers\list_data_controller;
use App\Http\Controllers\search;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/', function () {
// route::view('/alt','alert');
//     return view('welcome');
// route::get('/',[get_data::class,'data']);
// });
// route::view('/',[search::class,'search']);
route::view('/home', 'form');
route::view('/home', 'form');

route::post('/get_data', [search::class, 'get_data']);

route::post('/pass', [crud_controller::class, 'store']);
route::get('/retrive', [list_data_controller::class, 'show']);
route::get('/delete/{id}', [crud_controller::class, 'destroy']);
route::get('corection/{id}', [crud_controller::class, 'show']);
route::post('update', [crud_controller::class, 'update']);

Route::get('users', [UserController::class, 'index'])->name('users.index');
