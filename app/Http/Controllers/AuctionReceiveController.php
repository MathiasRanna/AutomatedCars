<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAuctionJob;
use App\Models\Auction;
use App\Models\AuctionImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

		$auction = Auction::create([
			'price' => $post['price'] ?? null,
			'bid_deadline' => $post['bidDeadline'] ?? null,
			'type' => $post['type'] ?? 'auctionsite',
			'custom_folder_name' => $post['customFolderName'] ?? null,
			'auction_date' => $post['auctionDate'] ?? null,
			'status' => 'received',
		]);

		$folderBase = $auction->custom_folder_name ?: ('Auction_' . now()->format('Y-m-d_His'));
		$folderBase = Str::slug($folderBase, '_');
		$disk = Storage::disk('public');
		$relativeDir = 'auctions/' . $folderBase;

		$stored = [];
		foreach ($images as $idx => $url) {
			try {
				$response = Http::timeout(30)->get($url);
				if (!$response->ok()) {
					continue;
				}
				$position = $idx + 1;
				$isSheet = $idx === (count($images) - 1);
				$extension = static::guessExtension($response->header('Content-Type'), $url);
				$filename = ($isSheet ? 'sheet' : 'img') . '_' . str_pad((string)$position, 3, '0', STR_PAD_LEFT) . '.' . $extension;
				$path = $relativeDir . '/' . $filename;
				$disk->put($path, $response->body());

				$image = new AuctionImage([
					'stored_path' => $path,
					'is_sheet' => $isSheet,
					'position' => $position,
				]);
				$auction->images()->save($image);
				$stored[] = [
					'id' => $image->id,
					'path' => $path,
					'url' => $disk->url($path),
					'is_sheet' => $isSheet,
					'position' => $position,
				];
			} catch (\Throwable $e) {
				// Skip failed image but continue others
				continue;
			}
		}

		if (empty($stored)) {
			$auction->delete();
			return response()->json([
				'message' => 'Failed to download any images',
			], Response::HTTP_BAD_REQUEST)
				->header('Access-Control-Allow-Origin', '*')
				->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
				->header('Access-Control-Allow-Headers', 'Content-Type');
		}

		// Dispatch job to process auction with AI
		ProcessAuctionJob::dispatch($auction);

		return response()->json([
			'message' => 'Auction received',
			'auction' => [
				'id' => $auction->id,
				'price' => $auction->price,
				'bid_deadline' => $auction->bid_deadline,
				'type' => $auction->type,
				'custom_folder_name' => $auction->custom_folder_name,
				'auction_date' => optional($auction->auction_date)->toDateString(),
			],
			'images' => $stored,
		])
			->header('Access-Control-Allow-Origin', '*')
			->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
			->header('Access-Control-Allow-Headers', 'Content-Type');
	}

	private static function guessExtension(?string $contentType, string $url): string
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


