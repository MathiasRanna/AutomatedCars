<?php

namespace App\Models;

use App\Services\AuctionPostFormatter;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Auction extends Model
{
	use HasFactory;

	protected $fillable = [
		'price',
		'bid_deadline',
		'type',
		'custom_folder_name',
		'auction_date',
		'status',
		'extracted_data',
		'custom_post',
	];

	protected $casts = [
		'auction_date' => 'date',
		'extracted_data' => 'array',
	];

	public function images(): HasMany
	{
		return $this->hasMany(AuctionImage::class);
	}

	/**
	 * Get formatted auction post text
	 * Returns custom_post if set, otherwise generates from extracted_data
	 *
	 * @return string|null Formatted post or null if no extracted data
	 */
	public function getFormattedPost(): ?string
	{
		// Return custom post if it exists
		if (!empty($this->custom_post)) {
			return $this->custom_post;
		}

		// Otherwise generate from extracted data
		if (empty($this->extracted_data)) {
			return null;
		}

		$formatter = app(AuctionPostFormatter::class);
		return $formatter->format($this->extracted_data, $this->price);
	}
}


