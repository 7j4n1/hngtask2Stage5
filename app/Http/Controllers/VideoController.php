<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class VideoController extends Controller
{
    
    public function store(Request $request){
        $file = $request->all();
        $validate = Validator::make($file, [
            'file' => 'required|file|max:10000',
            'id' => 'required|string'
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->message()], 200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        }
        // Add a unique id to the uploaded file name
        $newFilename = uniqid()."_".$file->getClientOriginalName();
    }
}
