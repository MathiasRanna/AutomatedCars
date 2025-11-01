<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuctionImage extends Model
{
	use HasFactory;

	protected $fillable = [
		'auction_id',
		'stored_path',
		'is_sheet',
		'position',
	];

	public function auction(): BelongsTo
	{
		return $this->belongsTo(Auction::class);
	}
}


