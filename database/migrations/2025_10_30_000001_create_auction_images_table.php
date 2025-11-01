<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('auction_images', function (Blueprint $table) {
			$table->id();
			$table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
			$table->string('stored_path');
			$table->boolean('is_sheet')->default(false);
			$table->unsignedInteger('position')->default(0);
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('auction_images');
	}
};


