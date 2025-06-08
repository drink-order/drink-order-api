<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class SupabaseStorageService
{
    private Client $client;
    private string $baseUrl;
    private string $serviceKey;
    private string $bucket;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
        
        $this->baseUrl = rtrim(env('SUPABASE_URL'), '/');
        $this->serviceKey = env('SUPABASE_SERVICE_KEY');
        $this->bucket = env('SUPABASE_BUCKET', 'images');
        
        if (!$this->baseUrl || !$this->serviceKey) {
            throw new \Exception('Supabase configuration missing. Check SUPABASE_URL and SUPABASE_SERVICE_KEY in .env');
        }
    }

    /**
     * Upload file to Supabase Storage
     */
    public function uploadFile(UploadedFile $file, ?string $customPath = null): array
    {
        try {
            // Generate file path
            $fileName = $customPath ?: $this->generateFileName($file);
            $filePath = "images/{$fileName}";
            
            $response = $this->client->post(
                "{$this->baseUrl}/storage/v1/object/{$this->bucket}/{$filePath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->serviceKey}",
                        'Content-Type' => $file->getMimeType(),
                        'x-upsert' => 'true', // Allow overwrite
                    ],
                    'body' => file_get_contents($file->getPathname())
                ]
            );

            if ($response->getStatusCode() === 200) {
                $publicUrl = $this->getPublicUrl($filePath);
                
                return [
                    'success' => true,
                    'path' => $filePath,
                    'url' => $publicUrl,
                    'fileName' => $fileName
                ];
            }

            return [
                'success' => false,
                'error' => 'Upload failed with status: ' . $response->getStatusCode()
            ];

        } catch (GuzzleException $e) {
            Log::error('Supabase upload error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get public URL for a file
     */
    public function getPublicUrl(string $filePath): string
    {
        return "{$this->baseUrl}/storage/v1/object/public/{$this->bucket}/{$filePath}";
    }

    /**
     * Delete file from Supabase Storage
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            $response = $this->client->delete(
                "{$this->baseUrl}/storage/v1/object/{$this->bucket}/{$filePath}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->serviceKey}",
                    ]
                ]
            );

            return $response->getStatusCode() === 200;

        } catch (GuzzleException $e) {
            Log::error('Supabase delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * List files in bucket
     */
    public function listFiles(string $folder = '', int $limit = 100): array
    {
        try {
            $response = $this->client->post(
                "{$this->baseUrl}/storage/v1/object/list/{$this->bucket}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->serviceKey}",
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'limit' => $limit,
                        'prefix' => $folder,
                    ]
                ]
            );

            if ($response->getStatusCode() === 200) {
                return json_decode($response->getBody()->getContents(), true);
            }

            return [];

        } catch (GuzzleException $e) {
            Log::error('Supabase list files error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate unique filename
     */
    private function generateFileName(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $basename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Clean filename and add timestamp
        $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $basename);
        
        return time() . '_' . $cleanName . '.' . $extension;
    }

    /**
     * Check if bucket exists and is accessible
     */
    public function testConnection(): array
    {
        try {
            $response = $this->client->get(
                "{$this->baseUrl}/storage/v1/bucket/{$this->bucket}",
                [
                    'headers' => [
                        'Authorization' => "Bearer {$this->serviceKey}",
                    ]
                ]
            );

            if ($response->getStatusCode() === 200) {
                return [
                    'success' => true,
                    'message' => 'Connection successful'
                ];
            }

            return [
                'success' => false,
                'error' => 'Bucket not accessible'
            ];

        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage()
            ];
        }
    }
}