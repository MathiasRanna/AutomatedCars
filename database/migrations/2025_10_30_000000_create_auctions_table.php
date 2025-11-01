<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
	public function up(): void
	{
		Schema::create('auctions', function (Blueprint $table) {
			$table->id();
			$table->string('price')->nullable();
			$table->string('bid_deadline')->nullable(); // MM/DD or MM/DD HH:mm from scraper
			$table->string('type')->nullable();
			$table->string('custom_folder_name')->nullable();
			$table->date('auction_date')->nullable();
			$table->string('status')->default('received');
			$table->timestamps();
		});
	}

	public function down(): void
	{
		Schema::dropIfExists('auctions');
	}
};


