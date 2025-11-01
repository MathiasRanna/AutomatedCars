<?php

namespace App\Jobs;

use App\Models\Auction;
use App\Models\AuctionImage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DownloadAuctionImagesJob implements ShouldQueue
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
        public int $auctionId,
        public array $imageUrls
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $auction = Auction::find($this->auctionId);
        
        if (!$auction) {
            Log::error('Auction not found for image download', ['auction_id' => $this->auctionId]);
            return;
        }

        // Update status to downloading images
        $auction->update(['status' => 'downloading']);

        $folderBase = $auction->custom_folder_name ?: ('Auction_' . now()->format('Y-m-d_His'));
        $folderBase = Str::slug($folderBase, '_');
        $disk = Storage::disk('public');
        
        // Create date-based folder structure: auctions/YYYY-MM-DD/{carFolder}/
        $dateFolder = now()->format('Y-m-d');
        $relativeDir = 'auctions/' . $dateFolder . '/' . $folderBase;

        $stored = [];
        foreach ($this->imageUrls as $idx => $url) {
            try {
                $response = Http::timeout(30)->get($url);
                if (!$response->ok()) {
                    Log::warning('Failed to download image', [
                        'auction_id' => $this->auctionId,
                        'url' => $url,
                        'status' => $response->status(),
                    ]);
                    continue;
                }
                
                $position = $idx + 1;
                $isSheet = $idx === (count($this->imageUrls) - 1);
                $extension = $this->guessExtension($response->header('Content-Type'), $url);
                $filename = ($isSheet ? 'sheet' : 'img') . '_' . str_pad((string)$position, 3, '0', STR_PAD_LEFT) . '.' . $extension;
                $path = $relativeDir . '/' . $filename;
                $disk->put($path, $response->body());

                $image = new AuctionImage([
                    'stored_path' => $path,
                    'is_sheet' => $isSheet,
                    'position' => $position,
                ]);
                $auction->images()->save($image);
                $stored[] = $image->id;
            } catch (\Throwable $e) {
                Log::error('Error downloading image', [
                    'auction_id' => $this->auctionId,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        if (empty($stored)) {
            $auction->update(['status' => 'failed']);
            Log::error('No images downloaded for auction', ['auction_id' => $this->auctionId]);
            $auction->delete();
            return;
        }

        // Update status to pending_processing to indicate images are ready and AI processing is queued
        $auction->update(['status' => 'pending_processing']);

        // Dispatch job to process auction with AI
        // ProcessAuctionJob will immediately update status to 'processing' when it starts
        ProcessAuctionJob::dispatch($auction);

        Log::info('Images downloaded successfully', [
            'auction_id' => $this->auctionId,
            'images_count' => count($stored),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $auction = Auction::find($this->auctionId);
        if ($auction) {
            $auction->update(['status' => 'failed']);
        }
        
        Log::error('Image download job failed permanently', [
            'auction_id' => $this->auctionId,
            'error' => $exception->getMessage(),
        ]);
    }

    private function guessExtension(?string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        if ($contentType && isset($map[$contentType])) {
            return $map[$contentType];
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        return in_array($ext, array_values($map), true) ? $ext : 'jpg';
    }
}

