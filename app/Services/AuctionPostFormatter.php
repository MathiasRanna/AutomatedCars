<?php

namespace App\Services;

use Carbon\Carbon;

class AuctionPostFormatter
{
    /**
     * Format auction post from extracted data
     *
     * @param array $extractedData The extracted_data from the auction
     * @param string|null $priceEUR The price in EUR (from auction model)
     * @return string Formatted post text
     */
    public function format(array $extractedData, ?string $priceEUR = null): string
    {
        $make = $extractedData['make'] ?? 'N/A';
        $model = $extractedData['model'] ?? 'N/A';
        $year = $extractedData['year'] ?? 'N/A';
        $engine = $extractedData['engine'] ?? 'N/A';
        $mileage = $extractedData['mileage'] ?? 0;
        $sellingPoints = $extractedData['sellingPoints'] ?? [];
        $damageNotes = $extractedData['damageNotes'] ?? [];
        $exteriorGrade = $extractedData['exteriorGrade'] ?? 'N/A';
        $interiorGrade = $extractedData['interiorGrade'] ?? 'N/A';
        $auctionDeadline = $extractedData['auctionDeadline'] ?? null;

        // Format the title
        $post = "NEW! Arriving to the auction {$make} {$model} {$year}\n\n";

        // Price section
        $price = $priceEUR ?: '0';
        $post .= "💶 Starting price: " . $this->formatPrice($price) . "€\n\n";

        // Bidding deadline
        if ($auctionDeadline) {
            $deadlineDate = $this->subtractOneDay($auctionDeadline);
            $post .= "🕒 Bidding deadline: {$deadlineDate} by the end of the day at 21:00\n\n";
        }

        $post .= "****\n\n";

        // Car details
        $post .= "Car details:\n\n";
        $post .= "📌 Engine: {$engine}\n\n";
        $post .= "📌 Mileage: " . $this->formatPrice($mileage) . " km\n\n";

        // Selling points
        if (!empty($sellingPoints)) {
            foreach ($sellingPoints as $point) {
                $post .= "📌 {$point}\n";
            }
            $post .= "\n";
        }

        // Damage notes
        if (!empty($damageNotes)) {
            foreach ($damageNotes as $note) {
                $post .= "📌 {$note}\n";
            }
            $post .= "\n";
        }

        // Grades
        $post .= "✅ Exterior grade: {$exteriorGrade}\n";
        $post .= "✅ Interior grade: {$interiorGrade}\n\n";

        $post .= "****\n\n";

        // Calculator link
        $post .= "Calculate the final price of the vehicle here 🏁\n\n";
        $post .= "www.jpcars.ee/calculator\n\n";

        $post .= "****\n\n";

        // Contact info
        $post .= "Contact us:\n\n";
        $post .= "✉️ E-mail: orders@jpcars.ee\n\n";
        $post .= "📞 Phone: +37256992959 (WhatsApp)\n\n";
        $post .= "🇪🇪🇫🇮🇬🇧🇷🇺🇮🇹\n";

        return $post;
    }

    /**
     * Format price with thousand separators
     *
     * @param int|float|string $price
     * @return string
     */
    private function formatPrice($price): string
    {
        // Convert to number if string
        $numericPrice = is_numeric($price) ? (float) $price : 0;
        
        // Format with spaces as thousand separators (European style)
        return number_format($numericPrice, 0, ',', ' ');
    }

    /**
     * Subtract one day from the deadline date
     * Expects format: "dd.mm.yyyy" and returns same format
     *
     * @param string $deadline Date string in format "dd.mm.yyyy"
     * @return string
     */
    private function subtractOneDay(string $deadline): string
    {
        try {
            // Parse the date (dd.mm.yyyy)
            $date = Carbon::createFromFormat('d.m.Y', $deadline);
            
            // Subtract one day
            $date->subDay();
            
            // Return in same format
            return $date->format('d.m.Y');
        } catch (\Exception $e) {
            // If parsing fails, return original
            return $deadline;
        }
    }
}

