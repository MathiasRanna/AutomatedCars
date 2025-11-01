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
    private string $provider;

    public function __construct()
    {
        // Configure your AI service (Google Gemini, OpenAI, etc.)
        $this->apiKey = config('services.ai.api_key', env('AI_API_KEY'));
        $this->apiUrl = config('services.ai.api_url', env('AI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models'));
        $this->model = config('services.ai.model', env('AI_MODEL', 'gemini-flash-lite-latest'));
        $this->provider = config('services.ai.provider', env('AI_PROVIDER', 'gemini'));
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

            if ($this->provider === 'gemini') {
                // Gemini uses inlineData format (note: mimeType not mime_type)
                $prepared[] = [
                    'inlineData' => [
                        'mimeType' => $mimeType,
                        'data' => $base64,
                    ],
                ];
            } else {
                // OpenAI format
                $prepared[] = [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => "data:{$mimeType};base64,{$base64}",
                    ],
                ];
            }
        }

        return $prepared;
    }

    /**
     * Build the prompt for AI extraction
     * Matches the Node.js implementation that works well
     */
    private function buildExtractionPrompt(array $imageData, array $existingData = []): array
    {
        $prompt = "You will receive one car auction sheet.";
        
        // Incorporate existing data if available to provide context
        if (!empty($existingData)) {
            $contextParts = [];
            if (!empty($existingData['price'])) {
                $contextParts[] = "Price: " . $existingData['price'];
            }
            if (!empty($existingData['bid_deadline'])) {
                $contextParts[] = "Bid deadline: " . $existingData['bid_deadline'];
            }
            if (!empty($existingData['auction_date'])) {
                $contextParts[] = "Auction date: " . $existingData['auction_date'];
            }
            if (!empty($existingData['type'])) {
                $contextParts[] = "Auction type: " . $existingData['type'];
            }
            
            if (!empty($contextParts)) {
                $prompt .= "\n\nAdditional context from scraper:\n" . implode("\n", $contextParts) . "\n\nUse this context to complement or verify information extracted from the images.";
            }
        }
        
        $prompt .= "\n\nYour task is to:
- Read text/markers in the images (OCR) and infer missing details.
- Translate non-English text to English if needed.
- Extract the vehicle information.
- Extract the vehicle selling points from Selling points section and Notes(repairs,defects,condition, etc.) section.
- Extract vechicle damage notes from Surveyors report (USS use column)
- If car has some exterior scratches or dents, then just write next 'Some exterior scratches or dents (see auction map)'.
- If there are scratches on alloy wheels or windshield, then you can mention them also'.
- If car engine is in cc format, then convert it to liters.
- Japanese car auction sheets use \"H\", \"S\", \"R\" etc. for years based on the Japanese imperial calendar, convert them to Gregorian calendar.
- Return everything in English in structured JSON format.

Required fields:
- make
- model
- year
- engine
- mileage (number only)
- sellingPoints[] (bullet points, in English)
- damageNotes[] (bullet points, in English)
- exteriorGrade (number from 1–6)
- interiorGrade (letter from A–F)

Respond with only JSON, no explanations or comments. 
Example:
{  \"make\": \"\",
  \"model\": \"\",
  \"year\": \"\",
  \"engine\": \"\",
  \"mileage\": 0,
  \"auctionDeadline\": \"dd.mm.yyyy\",
  \"sellingPoints\": [],
  \"damageNotes\": [],
  \"exteriorGrade\": 0,
  \"interiorGrade\": \"\"
}";

        if ($this->provider === 'gemini') {
            // Gemini format: contents array with parts (prompt first, then images)
            // Matches Node.js: [prompt, imagePart]
            $parts = array_merge(
                [['text' => $prompt]],
                $imageData
            );

            return [
                'contents' => [
                    [
                        'parts' => $parts,
                    ],
                ],
                'generationConfig' => [
                    'response_mime_type' => 'application/json',
                ],
            ];
        } else {
            // OpenAI format
            $systemInstruction = "You are an expert at analyzing car auction listings. Extract structured information from auction sheets.";
            
            $messages = [
                [
                    'role' => 'system',
                    'content' => $systemInstruction,
                ],
                [
                    'role' => 'user',
                    'content' => array_merge(
                        [
                            [
                                'type' => 'text',
                                'text' => $prompt,
                            ],
                        ],
                        $imageData
                    ),
                ],
            ];

            return $messages;
        }
    }

    /**
     * Make the actual AI API request
     */
    private function makeAIRequest(array $payload): array
    {
        if ($this->provider === 'gemini') {
            // Gemini API: POST to /{model}:generateContent?key={api_key}
            $url = $this->apiUrl . '/' . $this->model . ':generateContent?key=' . urlencode($this->apiKey);
            
            $response = Http::timeout(120)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($url, $payload);

            if (!$response->successful()) {
                throw new \Exception('Gemini API request failed: ' . $response->body());
            }

            return $response->json();
        } else {
            // OpenAI API
            $response = Http::timeout(120)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl, array_merge($payload, [
                    'model' => $this->model,
                    'response_format' => ['type' => 'json_object'],
                    'max_tokens' => 2000,
                ]));

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            return $response->json();
        }
    }

    /**
     * Parse AI response into structured data
     */
    private function parseAIResponse(array $response): array
    {
        if ($this->provider === 'gemini') {
            // Gemini response format: candidates[0].content.parts[0].text
            $content = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        } else {
            // OpenAI response format: choices[0].message.content
            $content = $response['choices'][0]['message']['content'] ?? '';
        }
        
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
     * Matches the Node.js processGeminiResponse function
     */
    private function extractJson(string $text): string
    {
        // Trim whitespace
        $jsonText = trim($text);
        
        // Remove ```json ... ``` or ``` ... ``` fences if present
        if (strpos($jsonText, '```') === 0) {
            $jsonText = preg_replace('/^```[a-zA-Z]*\n([\s\S]*?)\n```\s*$/m', '$1', $jsonText);
            $jsonText = trim($jsonText);
        }
        
        // Fallback: find the first { and last }
        $firstBrace = strpos($jsonText, '{');
        $lastBrace = strrpos($jsonText, '}');
        
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $jsonText = substr($jsonText, $firstBrace, $lastBrace - $firstBrace + 1);
        }

        return $jsonText;
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
