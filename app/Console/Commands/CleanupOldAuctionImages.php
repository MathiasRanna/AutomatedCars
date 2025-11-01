<?php

namespace App\Console\Commands;

use App\Models\Auction;
use App\Models\AuctionImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupOldAuctionImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'auctions:cleanup-old-images {--days=14 : Number of days to keep images}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete auction images older than specified days (default: 14 days)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);
        
        $this->info("Cleaning up auction images older than {$days} days (before {$cutoffDate->format('Y-m-d')})...");

        $disk = Storage::disk('public');
        $auctionsBaseDir = 'auctions';
        
        if (!$disk->exists($auctionsBaseDir)) {
            $this->warn("Auctions directory does not exist.");
            return Command::SUCCESS;
        }

        $deletedFolders = 0;
        $deletedAuctions = 0;
        $deletedImages = 0;

        // Get all date folders in auctions directory
        $dateFolders = $disk->directories($auctionsBaseDir);
        
        foreach ($dateFolders as $dateFolder) {
            // Extract date from folder name (e.g., "auctions/2025-11-01" -> "2025-11-01")
            $folderName = basename($dateFolder);
            
            // Try to parse the date
            try {
                $folderDate = \Carbon\Carbon::createFromFormat('Y-m-d', $folderName);
            } catch (\Exception $e) {
                // Skip folders that don't match date format
                $this->warn("Skipping folder '{$folderName}' (invalid date format)");
                continue;
            }

            // Check if folder date is older than cutoff
            if ($folderDate->lt($cutoffDate)) {
                $this->line("Deleting folder: {$dateFolder}");
                
                // Delete all files in this date folder recursively
                $allFiles = $disk->allFiles($dateFolder);
                foreach ($allFiles as $file) {
                    $disk->delete($file);
                    $deletedImages++;
                }
                
                // Delete all subdirectories (car folders)
                $subDirs = $disk->directories($dateFolder);
                foreach ($subDirs as $subDir) {
                    $disk->deleteDirectory($subDir);
                    $deletedFolders++;
                }
                
                // Delete the date folder itself
                $disk->deleteDirectory($dateFolder);
                $deletedFolders++;

                // Delete related database records
                $this->deleteDatabaseRecords($folderName, $deletedAuctions);
            }
        }

        $this->info("Cleanup completed!");
        $this->info("Deleted {$deletedFolders} folders, {$deletedImages} image files, {$deletedAuctions} auction records.");

        return Command::SUCCESS;
    }

    /**
     * Delete auction and image records from database for the given date folder
     */
    private function deleteDatabaseRecords(string $dateFolder, int &$deletedAuctions): void
    {
        // Find all image records that have paths starting with this date folder
        $images = AuctionImage::where('stored_path', 'like', "auctions/{$dateFolder}/%")->get();
        
        if ($images->isEmpty()) {
            return;
        }

        // Get unique auction IDs from the images
        $auctionIds = $images->pluck('auction_id')->unique();
        
        // Delete image records
        $deletedImageCount = AuctionImage::whereIn('id', $images->pluck('id'))->delete();
        
        // Delete auction records that have no remaining images
        foreach ($auctionIds as $auctionId) {
            $auction = Auction::find($auctionId);
            if ($auction && $auction->images()->count() === 0) {
                $auction->delete();
                $deletedAuctions++;
            }
        }
    }
}
