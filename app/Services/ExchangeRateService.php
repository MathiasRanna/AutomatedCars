<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    private string $apiKey;
    private string $apiUrl;
    private const CACHE_DURATION = 24 * 60 * 60; // 24 hours in seconds
    private const CACHE_KEY = 'jpy_to_eur_rate';

    public function __construct()
    {
        $this->apiKey = config('services.exchange_rate.api_key', env('EXCHANGE_API_KEY'));
        $this->apiUrl = 'https://v6.exchangerate-api.com/v6/' . $this->apiKey . '/latest/JPY';
    }

    /**
     * Get JPY to EUR exchange rate (cached for 24 hours)
     */
    public function getJPYtoEURRate(): ?float
    {
        // Check cache first
        $cachedRate = Cache::get(self::CACHE_KEY);
        if ($cachedRate !== null) {
            return $cachedRate;
        }

        // Fetch new rate
        $rate = $this->fetchRate();
        
        if ($rate !== null) {
            // Cache for 24 hours
            Cache::put(self::CACHE_KEY, $rate, self::CACHE_DURATION);
        }

        return $rate;
    }

    /**
     * Fetch exchange rate from API
     */
    private function fetchRate(): ?float
    {
        try {
            $response = Http::timeout(10)->get($this->apiUrl);

            if (!$response->successful()) {
                Log::warning('Exchange rate API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();

            if (($data['result'] ?? '') !== 'success' || !isset($data['conversion_rates']['EUR'])) {
                Log::warning('Invalid exchange rate API response', ['data' => $data]);
                return null;
            }

            return (float) $data['conversion_rates']['EUR'];
        } catch (\Exception $e) {
            Log::error('Error fetching exchange rate', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Convert JPY amount to EUR
     * 
     * @param float $jpyAmount JPY amount to convert
     * @return array|null Returns ['rate' => float, 'eurAmount' => float] or null on error
     */
    public function convertJPYtoEUR(float $jpyAmount): ?array
    {
        $rate = $this->getJPYtoEURRate();
        
        if ($rate === null) {
            return null;
        }

        return [
            'rate' => $rate,
            'eurAmount' => $jpyAmount * $rate,
        ];
    }

    /**
     * Parse JPY price from formatted string (e.g., "¥7,980,000" -> 7980000)
     * 
     * @param string $priceString Formatted price string
     * @return float|null Parsed JPY amount or null on failure
     */
    public function parseJPYPrice(string $priceString): ?float
    {
        // Remove currency symbols and whitespace
        $cleaned = preg_replace('/[¥\s]/', '', $priceString);
        
        // Remove commas
        $cleaned = str_replace(',', '', $cleaned);
        
        // Extract numeric value
        if (preg_match('/(\d+(?:\.\d+)?)/', $cleaned, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Round up to nearest 100
     * 
     * @param float $amount Amount to round up
     * @return int Rounded up amount
     */
    public function roundUpToHundreds(float $amount): int
    {
        return (int) ceil($amount / 100) * 100;
    }

    /**
     * Convert JPY price string to EUR and round up to nearest 100
     * 
     * @param string $jpyPriceString Formatted JPY price (e.g., "¥7,980,000")
     * @return array|null Returns ['originalJPY' => float, 'rate' => float, 'eurAmount' => float, 'roundedEUR' => int] or null on error
     */
    public function convertAndRound(string $jpyPriceString): ?array
    {
        $jpyAmount = $this->parseJPYPrice($jpyPriceString);
        
        if ($jpyAmount === null) {
            return null;
        }

        $conversion = $this->convertJPYtoEUR($jpyAmount);
        
        if ($conversion === null) {
            return null;
        }

        return [
            'originalJPY' => $jpyAmount,
            'rate' => $conversion['rate'],
            'eurAmount' => $conversion['eurAmount'],
            'roundedEUR' => $this->roundUpToHundreds($conversion['eurAmount']),
        ];
    }
}

