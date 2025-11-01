<?php

namespace App\Http\Controllers;

use App\Jobs\CleanupOldImagesJob;
use App\Jobs\DownloadAuctionImagesJob;
use App\Models\Auction;
use App\Services\ExchangeRateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuctionReceiveController extends Controller
{
	public function __invoke(Request $request)
	{
		$validated = $request->validate([
			'post.price' => ['nullable', 'string'],
			'post.bidDeadline' => ['nullable', 'string'],
			'post.type' => ['nullable', 'string'],
			'post.customFolderName' => ['nullable', 'string'],
			'post.auctionDate' => ['nullable', 'date'],
			'images' => ['required', 'array', 'min:1'],
			'images.*' => ['required', 'url'],
		]);

		$post = $validated['post'] ?? [];
		$images = $validated['images'];

		// Convert JPY price to EUR and round up to nearest 100
		$priceEUR = 0; // Default to 0 EUR
		if (!empty($post['price'])) {
			$exchangeService = app(ExchangeRateService::class);
			$conversion = $exchangeService->convertAndRound($post['price']);
			
			if ($conversion !== null) {
				$priceEUR = $conversion['roundedEUR'];
				Log::info('Price converted', [
					'original_jpy' => $conversion['originalJPY'],
					'rate' => $conversion['rate'],
					'eur_amount' => $conversion['eurAmount'],
					'rounded_eur' => $priceEUR,
				]);
			} else {
				Log::warning('Failed to convert price', ['price_string' => $post['price']]);
			}
		}

		$auction = Auction::create([
			'price' => (string) $priceEUR,
			'bid_deadline' => $post['bidDeadline'] ?? null,
			'type' => $post['type'] ?? 'auctionsite',
			'custom_folder_name' => $post['customFolderName'] ?? null,
			'auction_date' => $post['auctionDate'] ?? null,
			'status' => 'received',
		]);

		// Dispatch job to download images asynchronously
		// This allows the controller to respond immediately
		DownloadAuctionImagesJob::dispatch($auction->id, $images);

		// Cleanup old images (triggered on each request to keep storage clean)
		// Runs asynchronously via job queue to avoid blocking the response
		try {
			CleanupOldImagesJob::dispatch()->afterResponse();
		} catch (\Exception $e) {
			// Log but don't fail the request if cleanup fails
			Log::warning('Failed to dispatch image cleanup job', ['error' => $e->getMessage()]);
		}

		return response()->json([
			'message' => 'Auction received and processing',
			'auction' => [
				'id' => $auction->id,
				'price' => $auction->price,
				'bid_deadline' => $auction->bid_deadline,
				'type' => $auction->type,
				'custom_folder_name' => $auction->custom_folder_name,
				'auction_date' => optional($auction->auction_date)->toDateString(),
				'status' => $auction->status,
			],
			'status' => 'processing',
		])
			->header('Access-Control-Allow-Origin', '*')
			->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
			->header('Access-Control-Allow-Headers', 'Content-Type');
	}
}


