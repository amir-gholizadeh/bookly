<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiDocController extends AbstractController
{
    #[Route('/api/examples', name: 'api_examples')]
    public function apiExamples(): Response
    {
        return $this->render('api/examples.html.twig', [
            'examples' => [
                [
                    'title' => 'Authentication',
                    'endpoint' => '/api/token',
                    'method' => 'POST',
                    'request' => json_encode([
                        'username' => 'user@example.com',
                        'password' => 'password123',
                    ], JSON_PRETTY_PRINT),
                    'response' => json_encode([
                        'token' => 'eyJ0eXAiOiJKV1QiLCJhbGc...',
                        'refresh_token' => 'abc123def456...',
                    ], JSON_PRETTY_PRINT),
                ],
                [
                    'title' => 'Get Books List',
                    'endpoint' => '/api/books?page=1&limit=10',
                    'method' => 'GET',
                    'request' => '',
                    'response' => json_encode([
                        'items' => [
                            [
                                'id' => 1,
                                'title' => 'Sample Book',
                                'author' => 'John Doe',
                                'genre' => 'Fiction',
                                'averageRating' => 4.5,
                                'reviewCount' => 10,
                                'coverImageUrl' => '/uploads/cover_images/sample.jpg',
                            ],
                        ],
                        'pagination' => [
                            'total' => 25,
                            'page' => 1,
                            'limit' => 10,
                            'pages' => 3,
                        ],
                    ], JSON_PRETTY_PRINT),
                ],
            ],
        ]);
    }
}
