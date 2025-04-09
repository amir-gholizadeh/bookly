<?php

namespace App\Service;

use App\Entity\Book;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class GoogleBooksService
{
    // The base URL for Google Books API
    private const API_URL = 'https://www.googleapis.com/books/v1';
    private Client $client;
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        // Create a client with no base_uri
        $this->client = new Client([
            'timeout' => 5.0,
        ]);
        $this->logger = $logger;
    }

    /**
     * Search for books by title and/or author.
     */
    public function searchBooks(?string $title = null, ?string $author = null, int $maxResults = 10): array
    {
        if (!$title && !$author) {
            return [];
        }

        $query = [];
        if ($title) {
            $query[] = 'intitle:'.urlencode($title);
        }
        if ($author) {
            $query[] = 'inauthor:'.urlencode($author);
        }

        try {
            $queryString = implode('+', $query);
            $this->logger->debug('Searching Google Books API with query: '.$queryString);

            // Use the complete URL directly
            $url = self::API_URL.'/volumes?q='.$queryString.'&maxResults='.$maxResults;
            $this->logger->debug('Full search URL: '.$url);

            $response = $this->client->request('GET', $url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['items']) || empty($data['items'])) {
                return [];
            }

            return array_map([$this, 'mapBookResult'], $data['items']);
        } catch (GuzzleException $e) {
            $this->logger->error('Google Books API error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get detailed book information by Google Books ID.
     */
    public function getBookDetails(string $googleBooksId): ?array
    {
        try {
            $this->logger->debug('Getting book details from Google Books API: '.$googleBooksId);

            // Use the complete URL directly
            $url = self::API_URL.'/volumes/'.$googleBooksId;
            $this->logger->debug('Full details URL: '.$url);

            $response = $this->client->request('GET', $url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['volumeInfo'])) {
                return null;
            }

            return $this->mapBookResult($data);
        } catch (GuzzleException $e) {
            $this->logger->error('Google Books API error when getting details: '.$e->getMessage(), [
                'googleBooksId' => $googleBooksId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a Book entity from Google Books API data.
     */
    public function createBookFromApiData(array $data): Book
    {
        $book = new Book();
        $book->setTitle($data['title'] ?? 'Unknown Title');
        $book->setAuthor($data['authors'] ?? 'Unknown Author');
        $book->setNumberOfPages($data['pageCount'] ?? 0);

        // Handle HTML in description - decode HTML entities and sanitize
        $description = $data['description'] ?? 'No description available.';
        $description = html_entity_decode($description);
        // Allow certain safe HTML tags
        $description = strip_tags($description, '<p><br><b><i><strong><em><ul><ol><li><h1><h2><h3><h4><h5>');

        $book->setSummary($description);
        $book->setGenre($data['categories'][0] ?? 'Uncategorized');

        return $book;
    }

    /**
     * Map Google Books API result to a simplified array structure.
     */
    private function mapBookResult(array $item): array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];

        return [
            'id' => $item['id'] ?? null,
            'title' => $volumeInfo['title'] ?? 'Unknown Title',
            'authors' => isset($volumeInfo['authors']) ? implode(', ', $volumeInfo['authors']) : 'Unknown Author',
            'description' => $volumeInfo['description'] ?? 'No description available.',
            'pageCount' => $volumeInfo['pageCount'] ?? 0,
            'categories' => $volumeInfo['categories'] ?? ['Uncategorized'],
            'averageRating' => $volumeInfo['averageRating'] ?? 0,
            'imageLinks' => [
                'thumbnail' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
                'small' => $volumeInfo['imageLinks']['smallThumbnail'] ?? null,
            ],
            'publishedDate' => $volumeInfo['publishedDate'] ?? null,
            'publisher' => $volumeInfo['publisher'] ?? null,
            'language' => $volumeInfo['language'] ?? null,
        ];
    }
}
