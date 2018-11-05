<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Utils\AccountHelper;

class DebugController extends Controller
{
    public function test(Request $req)
    {
        dump('hello');
    }

}
