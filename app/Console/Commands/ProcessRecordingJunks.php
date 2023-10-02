<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Illuminate\Support\Facades\Storage;

class ProcessRecordingJunks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-recording-junks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process recording chunks from RabbitMQ queue';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Setup Connection
        $connection = new AMPQConnection('localhost', 5672, 'guest', 'guest');

        $channel = $connection->channel();

        /*
            name: $queue
            passive: false
            durable: true // the queue will survive server restarts
            exclusive: false // the queue can be accessed in other channels
            auto_delete: false //the queue won't be deleted once the channel is closed.
        */
        $channel->queue_declare('process-recording-junks', false, true, false, false);

        // to identify last chunk
        // $isLastChunk = false;

        function callback($message) {
            $chunkData = json_decode($message->body, true);
            
            $tempPath = "app/temp/".$chunkData['id']."/";
            $path = storage_path($tempPath);

            $isLastChunk = $chunkData['isLast_Chunk'];

            // Store the chunk in a temp directory
            file_put_contents($path . $chunkData['chunk_order'], base64_decode($chunkData['data']));

            // Acknowledge the message
            $message->ack();

            // check if it is the last expected chunk
            if ($isLastChunk) {
                // Assemble and save the complete recording
                $this->reassembleAndSaveVideo($path, $chunkData['id']);
            }

            
        }

        // Consume messages from the queue
        $channel->basic_consume('process-recording-junks', '', false, false, false, false, 'callback');


        while($channel->is_consuming())
        {
            $channel->wait();
        }

        $channel->close();
        $connection->close();

    }

    private function reassembleAndSaveVideo($tempChunkDirectory, $id)
    {
        // Get a list of all files in the directory
        $chunkFiles = Storage::files($tempChunkDirectory);

        // Sort the chunk files in the correct order by filename
        sort($chunkFiles);

        // Initialize a variable to store the concatenated video data
        $concatenatedVideoData = '';

        // Read and concatenate the contents of each chunk file
        foreach ($chunkFiles as $chunkFile) {
            $chunkData = Storage::get($chunkFile);
            $concatenatedVideoData .= $chunkData;
        }

        // Define the path where the complete video will be saved
        $videoPath = storage_path('app/recordings/') . 'complete_' . $id . '.mp4';

        // Save the complete video to the specified path
        Storage::put($videoPath, $concatenatedVideoData);

        // Clean up: Delete the temporary chunk files
        foreach ($chunkFiles as $chunkFile) {
            Storage::delete($chunkFile);
        }

        // $this->info('Video reassembled and saved successfully.');
    }
}
