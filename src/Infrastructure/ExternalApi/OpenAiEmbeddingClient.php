<?php

declare(strict_types=1);

namespace App\Infrastructure\ExternalApi;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * OpenAiEmbeddingClient - Generate embeddings using OpenAI API
 * 
 * Uses text-embedding-3-small model to generate 1536-dimensional vectors
 * for semantic search. Embeddings are cached to reduce API calls and costs.
 */
final readonly class OpenAiEmbeddingClient
{
    private const MODEL = 'text-embedding-3-small';
    private const DIMENSIONS = 1536;
    private const CACHE_TTL = 2592000; // 30 days

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private LoggerInterface $logger,
        private string $apiKey
    ) {
    }

    /**
     * Generate embedding for text
     * 
     * @param string $text Text to embed (e.g., material description)
     * @return array 1536-dimensional float array
     * @throws \RuntimeException If API call fails
     */
    public function generateEmbedding(string $text): array
    {
        // Check cache first
        $cacheKey = 'embedding_' . md5($text);
        $cachedItem = $this->cache->getItem($cacheKey);
        
        if ($cachedItem->isHit()) {
            $this->logger->debug('Embedding cache hit', ['text_length' => strlen($text)]);
            return $cachedItem->get();
        }

        // Call OpenAI API
        $this->logger->info('Generating embedding via OpenAI', [
            'text_length' => strlen($text),
            'model' => self::MODEL,
        ]);

        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/embeddings', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'input' => $text,
                    'dimensions' => self::DIMENSIONS,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();
            $embedding = $data['data'][0]['embedding'] ?? null;

            if (!$embedding || count($embedding) !== self::DIMENSIONS) {
                throw new \RuntimeException('Invalid embedding response from OpenAI');
            }

            // Cache the result
            $cachedItem->set($embedding);
            $cachedItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cachedItem);

            $this->logger->info('Embedding generated and cached', [
                'text_length' => strlen($text),
                'dimensions' => count($embedding),
                'usage_tokens' => $data['usage']['total_tokens'] ?? 'N/A',
            ]);

            return $embedding;

        } catch (\Exception $e) {
            $this->logger->error('OpenAI embedding generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text),
            ]);
            throw new \RuntimeException('Failed to generate embedding: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Generate embeddings for multiple texts in batch
     * 
     * @param array $texts Array of texts to embed
     * @return array Array of embeddings (same order as input)
     */
    public function generateEmbeddingsBatch(array $texts): array
    {
        $embeddings = [];
        
        foreach ($texts as $text) {
            $embeddings[] = $this->generateEmbedding($text);
        }
        
        return $embeddings;
    }

    /**
     * Calculate cosine similarity between two embedding vectors
     * 
     * @param array $embedding1 First embedding vector
     * @param array $embedding2 Second embedding vector  
     * @return float Similarity score between -1 and 1 (higher = more similar)
     */
    public static function cosineSimilarity(array $embedding1, array $embedding2): float
    {
        if (count($embedding1) !== count($embedding2)) {
            throw new \InvalidArgumentException('Embeddings must have same dimensions');
        }

        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $magnitude1 += $embedding1[$i] ** 2;
            $magnitude2 += $embedding2[$i] ** 2;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0.0 || $magnitude2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}
