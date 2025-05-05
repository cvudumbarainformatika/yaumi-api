<?php

namespace App\Jobs;

use App\Models\ProductStockMutation;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessStockMutation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;
    
    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = [1, 5, 10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            ProductStockMutation::createMutation($this->data);
        } catch (\Exception $e) {
            Log::error('Error processing stock mutation: ' . $e->getMessage(), [
                'data' => $this->data,
                'exception' => $e
            ]);
            
            // Throw exception to trigger retry
            throw $e;
        }
    }
}