<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class RecordingController extends Controller
{
    public function startStream()
    {
        // Send a unique id to the user to be used to identify and store
        $recordID = uniqid();
        return response()->json(['id' => $recordID, 'message' => 'Ready to accept stream with this id'], 200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

    
    }

    public function index()
    {
        // Get a list of all files in the directory
        $recordFiles = Storage::files('app/recordings/');
        if(count($recordFiles) > 0)
            return response()->json(['recordings' => $recordFiles], 200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

        return response()->json(['message' => 'No recording found'], 200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    }


    public function uploadChunk(Request $request)
    {
        $chunk = $request->all();
        $validate = Validator::make($chunk, [
            'id' => 'required|string',
            'isLast_Chunk' => 'required',
            'chunk_order' => 'required',
            'data' => 'required|string'
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->message()], 200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
        }

        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
        $channel = $connection->channel();
        $channel->queue_declare('process-recording-junks', false, false, false, false);

        $messageData = [
            'id' => $request->id,
            'data' => $request->data,
            'isLast_Chunk' => $request->isLast_Chunk,
            'chunk_order' => $request->chunk_order,
        ];

        $message = new AMQPMessage(json_encode($messageData));
        $channel->basic_publish($message, '', 'process-recording-junks');

        $channel->close();
        $connection->close();

        return response()->json(['message' => 'Chunk uploaded successfully'], 200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);

    }

        
    // public function store(Request $request){
    //     $file = $request->all();
    //     $validate = Validator::make($file, [
    //         'file' => 'required|file|max:10000',
    //         'id' => 'required|string'
    //     ]);

    //     if ($validate->fails()) {
    //         return response()->json(['message' => $validate->message()], 200, [], JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    //     }
    //     // Add a unique id to the uploaded file name
    //     $newFilename = uniqid()."_".$file->getClientOriginalName();
    // }
}
