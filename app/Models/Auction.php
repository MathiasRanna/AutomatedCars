<?php

namespace App\Models;

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
	];

	protected $casts = [
		'auction_date' => 'date',
		'extracted_data' => 'array',
	];

	public function images(): HasMany
	{
		return $this->hasMany(AuctionImage::class);
	}
}


