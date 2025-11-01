<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AuctionAIService
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct()
    {
        // Configure your AI service (OpenAI, Anthropic, etc.)
        $this->apiKey = config('services.ai.api_key', env('AI_API_KEY'));
        $this->apiUrl = config('services.ai.api_url', env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'));
        $this->model = config('services.ai.model', env('AI_MODEL', 'gpt-4o'));
    }

    /**
     * Process auction images and extract structured data
     *
     * @param array $imagePaths Array of image paths (relative to storage)
     * @param array $existingData Existing auction data from scraper
     * @return array Extracted and processed data
     */
    public function processAuctionImages(array $imagePaths, array $existingData = []): array
    {
        try {
            // Convert image paths to base64 for API
            $imageData = $this->prepareImagesForAPI($imagePaths);

            // Build the prompt for extraction
            $messages = $this->buildExtractionPrompt($imageData, $existingData);

            // Make API request
            $response = $this->makeAIRequest($messages);

            // Parse and return structured data
            return $this->parseAIResponse($response);
        } catch (\Exception $e) {
            Log::error('AI processing failed', [
                'error' => $e->getMessage(),
                'images' => $imagePaths,
            ]);
            throw $e;
        }
    }

    /**
     * Prepare images for API by converting to base64
     */
    private function prepareImagesForAPI(array $imagePaths): array
    {
        $disk = Storage::disk('public');
        $prepared = [];

        foreach ($imagePaths as $path) {
            if (!$disk->exists($path)) {
                continue;
            }

            $imageContent = $disk->get($path);
            $mimeType = $this->guessMimeType($path);
            $base64 = base64_encode($imageContent);

            $prepared[] = [
                'type' => 'image_url',
                'image_url' => [
                    'url' => "data:{$mimeType};base64,{$base64}",
                ],
            ];
        }

        return $prepared;
    }

    /**
     * Build the prompt for AI extraction
     */
    private function buildExtractionPrompt(array $imageData, array $existingData): array
    {
        $systemPrompt = "You are an expert at analyzing car auction listings. Extract structured information from the provided images. 
        Focus on: make, model, year, VIN, mileage, condition, damage description, sale location, sale date/time, reserve price, 
        starting bid, and any other relevant details. Return a JSON object with the extracted fields.";

        $userPrompt = "Please analyze these auction images and extract all relevant information. ";
        
        if (!empty($existingData)) {
            $userPrompt .= "Here is some existing data from the scraper: " . json_encode($existingData) . ". ";
            $userPrompt .= "Use this to complement what you extract from the images, and correct any errors if needed. ";
        }
        
        $userPrompt .= "Return only valid JSON with these fields: make, model, year, vin, mileage, condition, damage_description, 
        location, sale_date, sale_time, reserve_price, starting_bid, notes (array of additional findings).";

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => array_merge(
                    [
                        [
                            'type' => 'text',
                            'text' => $userPrompt,
                        ],
                    ],
                    $imageData
                ),
            ],
        ];

        return $messages;
    }

    /**
     * Make the actual AI API request
     */
    private function makeAIRequest(array $messages): array
    {
        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->apiUrl, [
                'model' => $this->model,
                'messages' => $messages,
                'response_format' => ['type' => 'json_object'], // Force JSON response
                'max_tokens' => 2000,
            ]);

        if (!$response->successful()) {
            throw new \Exception('AI API request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Parse AI response into structured data
     */
    private function parseAIResponse(array $response): array
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        
        // Extract JSON from response (handle cases where AI wraps JSON in markdown)
        $json = $this->extractJson($content);
        
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('Failed to parse AI response as JSON', [
                'content' => $content,
                'error' => json_last_error_msg(),
            ]);
            return [];
        }

        return $data ?: [];
    }

    /**
     * Extract JSON from text (handles markdown code blocks)
     */
    private function extractJson(string $text): string
    {
        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            return $matches[1];
        }

        // Try to find JSON object
        if (preg_match('/\{.*\}/s', $text, $matches)) {
            return $matches[0];
        }

        return $text;
    }

    /**
     * Guess MIME type from file extension
     */
    private function guessMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
        ];

        return $mimeTypes[$extension] ?? 'image/jpeg';
    }
}
