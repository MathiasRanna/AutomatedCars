<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class AuctionController extends Controller
{
    /**
     * Show auctions index with list of dates
     */
    public function index()
    {
        // Get all unique dates from image paths (auctions/YYYY-MM-DD/...)
        $imagePaths = \App\Models\AuctionImage::where('stored_path', 'like', 'auctions/%/%')
            ->pluck('stored_path', 'auction_id')
            ->toArray();

        $dateCounts = [];
        
        foreach ($imagePaths as $auctionId => $path) {
            // Extract date from path: auctions/YYYY-MM-DD/carFolder/file.jpg
            if (preg_match('/auctions\/(\d{4}-\d{2}-\d{2})\//', $path, $matches)) {
                $date = $matches[1];
                if (!isset($dateCounts[$date])) {
                    $dateCounts[$date] = [];
                }
                $dateCounts[$date][$auctionId] = true;
            }
        }

        $dates = collect($dateCounts)
            ->map(function ($auctionIds, $date) {
                return [
                    'date' => $date,
                    'car_count' => count($auctionIds),
                ];
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('Auctions/Index', [
            'dates' => $dates,
        ]);
    }

    /**
     * Show cars for a specific date
     */
    public function showByDate(string $date)
    {
        // Get auctions where the stored images are in a date folder matching this date
        // We need to check the image paths to determine which date folder they're in
        $auctions = Auction::with('images')
            ->whereHas('images', function ($query) use ($date) {
                $query->where('stored_path', 'like', "auctions/{$date}/%");
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($auction) {
                $extracted = $auction->extracted_data ?? [];
                return [
                    'id' => $auction->id,
                    'make' => $extracted['make'] ?? 'N/A',
                    'model' => $extracted['model'] ?? 'N/A',
                    'year' => $extracted['year'] ?? 'N/A',
                    'price' => $auction->price,
                    'status' => $auction->status,
                    'custom_folder_name' => $auction->custom_folder_name,
                    'image_count' => $auction->images->count(),
                    'has_extracted_data' => !empty($auction->extracted_data),
                ];
            });

        return Inertia::render('Auctions/DateCars', [
            'date' => $date,
            'auctions' => $auctions,
        ]);
    }

    /**
     * Show single auction detail
     */
    public function show(Auction $auction)
    {
        $auction->load('images');

        // Get image URLs - use asset() for proper Inertia asset handling
        $images = $auction->images()
            ->orderBy('position')
            ->get()
            ->map(function ($image) {
                // Use relative URL path for Inertia (storage symlink handles the routing)
                // This ensures it works regardless of APP_URL configuration
                $url = '/storage/' . $image->stored_path;
                
                return [
                    'id' => $image->id,
                    'url' => $url,
                    'path' => $image->stored_path,
                    'is_sheet' => (bool) $image->is_sheet, // Convert to boolean to avoid showing "0" in frontend
                    'position' => (int) $image->position,
                ];
            })
            ->values() // Reset array keys to ensure sequential array
            ->toArray(); // Convert to array for Inertia

        // Get formatted post (custom or generated)
        $formattedPost = $auction->getFormattedPost();
        $customPost = $auction->custom_post;

        $extracted = $auction->extracted_data ?? [];

        return Inertia::render('Auctions/Show', [
            'auction' => [
                'id' => $auction->id,
                'price' => $auction->price,
                'bid_deadline' => $auction->bid_deadline,
                'type' => $auction->type,
                'custom_folder_name' => $auction->custom_folder_name,
                'auction_date' => $auction->auction_date?->toDateString(),
                'status' => $auction->status,
                'make' => $extracted['make'] ?? null,
                'model' => $extracted['model'] ?? null,
                'year' => $extracted['year'] ?? null,
                'extracted_data' => $extracted,
            ],
            'images' => $images,
            'formattedPost' => $formattedPost,
            'customPost' => $customPost,
        ]);
    }

    /**
     * Update auction custom post
     */
    public function updatePost(Request $request, Auction $auction)
    {
        $validated = $request->validate([
            'custom_post' => ['nullable', 'string'],
        ]);

        $auction->update([
            'custom_post' => $validated['custom_post'] ?? null,
        ]);

        return redirect()->back()->with('success', 'Post saved successfully!');
    }

    /**
     * Delete auction and all its images
     */
    public function destroy(Auction $auction)
    {
        $disk = Storage::disk('public');
        
        // Get all images for this auction
        $images = $auction->images;
        
        // Delete all image files from storage
        foreach ($images as $image) {
            if ($disk->exists($image->stored_path)) {
                $disk->delete($image->stored_path);
            }
        }
        
        // Delete the car folder if it exists (auctions/YYYY-MM-DD/{carFolder}/)
        // Extract folder path from first image
        if ($images->count() > 0) {
            $firstImage = $images->first();
            $pathParts = explode('/', $firstImage->stored_path);
            
            // Path structure: auctions/YYYY-MM-DD/{carFolder}/filename.jpg
            if (count($pathParts) >= 3) {
                $carFolder = $pathParts[0] . '/' . $pathParts[1] . '/' . $pathParts[2]; // auctions/YYYY-MM-DD/{carFolder}
                if ($disk->exists($carFolder)) {
                    $disk->deleteDirectory($carFolder);
                }
            }
        }
        
        // Delete all image records from database
        $auction->images()->delete();
        
        // Delete the auction record
        $auction->delete();
        
        // Extract date from first image path for redirect
        $date = null;
        if ($images->count() > 0) {
            $firstImagePath = $images->first()->stored_path;
            if (preg_match('/auctions\/(\d{4}-\d{2}-\d{2})\//', $firstImagePath, $matches)) {
                $date = $matches[1];
            }
        }
        
        // Redirect to date page if available, otherwise to auctions index
        if ($date) {
            return redirect()->route('auctions.date', $date)
                ->with('success', 'Auction deleted successfully');
        }
        
        return redirect()->route('auctions.index')
            ->with('success', 'Auction deleted successfully');
    }
}
