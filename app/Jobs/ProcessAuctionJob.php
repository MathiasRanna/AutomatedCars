<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Services\AuctionAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAuctionJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Auction $auction
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(AuctionAIService $aiService): void
    {
        try {
            // Update status to processing
            $this->auction->update(['status' => 'processing']);

            // Get all image paths for this auction
            $imagePaths = $this->auction->images()
                ->orderBy('position')
                ->pluck('stored_path')
                ->toArray();

            if (empty($imagePaths)) {
                throw new \Exception('No images found for auction');
            }

            // Prepare existing data for AI context
            $existingData = [
                'price' => $this->auction->price,
                'bid_deadline' => $this->auction->bid_deadline,
                'type' => $this->auction->type,
                'auction_date' => $this->auction->auction_date?->toDateString(),
            ];

            // Process images with AI
            $extractedData = $aiService->processAuctionImages($imagePaths, $existingData);

            // Update auction with extracted data and mark as processed
            $this->auction->update([
                'extracted_data' => $extractedData,
                'status' => 'processed',
            ]);

            Log::info('Auction processed successfully', [
                'auction_id' => $this->auction->id,
                'extracted_fields' => array_keys($extractedData),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process auction', [
                'auction_id' => $this->auction->id,
                'error' => $e->getMessage(),
            ]);

            // Update status to failed
            $this->auction->update(['status' => 'failed']);

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->auction->update(['status' => 'failed']);
        
        Log::error('Auction processing job failed permanently', [
            'auction_id' => $this->auction->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
