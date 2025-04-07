<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Review;
use App\Entity\User;
use App\Service\ApiRequestValidator;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @OA\Tag(name="API", description="Book Review API endpoints")
 */
#[Route('/api')]
class ApiController extends AbstractController
{
    /**
     * List all books with pagination.
     *
     * @OA\Get(
     *     path="/api/books",
     *     summary="Get a list of books",
     *     tags={"Books"}
     * )
     *
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     @OA\Schema(type="integer", default=1)
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Number of books per page",
     *     @OA\Schema(type="integer", default=10)
     * )
     *
     * @OA\Parameter(
     *     name="sort",
     *     in="query",
     *     description="Sort by field (title_asc, title_desc, author_asc, author_desc, rating_asc, rating_desc)",
     *     @OA\Schema(type="string")
     * )
     *
     * @OA\Parameter(
     *     name="search",
     *     in="query",
     *     description="Search term for title, author, or genre",
     *     @OA\Schema(type="string")
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns a list of books",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="items", type="array", @OA\Items(ref=@Model(type=Book::class, groups={"book:list"}))),
     *        @OA\Property(property="pagination", type="object",
     *           @OA\Property(property="total", type="integer", example=25),
     *           @OA\Property(property="page", type="integer", example=1),
     *           @OA\Property(property="limit", type="integer", example=10),
     *           @OA\Property(property="pages", type="integer", example=3)
     *        )
     *     )
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/books', name: 'api_books_index', methods: ['GET'])]
    public function listBooks(Request $request, EntityManagerInterface $entityManager, SerializerInterface $serializer): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $sort = $request->query->get('sort');
        $search = $request->query->get('search');

        $bookRepository = $entityManager->getRepository(Book::class);
        $books = $bookRepository->findPaginatedBooks($page, $limit, $sort, $search);

        $totalBooks = count($books);
        $totalPages = ceil($totalBooks / $limit);

        $data = [
            'items' => $books,
            'pagination' => [
                'total' => $totalBooks,
                'page' => $page,
                'limit' => $limit,
                'pages' => $totalPages,
            ],
        ];

        return $this->json($data, 200, [], ['groups' => 'book:list']);
    }

    /**
     * Get details of a specific book.
     *
     * @OA\Get(
     *     path="/api/books/{id}",
     *     summary="Get a specific book's details",
     *     tags={"Books"}
     * )
     *
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Book ID",
     *     @OA\Schema(type="integer")
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns book details",
     *     @OA\JsonContent(ref=@Model(type=Book::class, groups={"book:detail"}))
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Book not found"
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/books/{id}', name: 'api_books_show', methods: ['GET'])]
    public function getBook(Book $book): JsonResponse
    {
        return $this->json($book, 200, [], ['groups' => 'book:detail']);
    }

    /**
     * Get reviews for a specific book.
     *
     * @OA\Get(
     *     path="/api/books/{id}/reviews",
     *     summary="Get reviews for a specific book",
     *     tags={"Books", "Reviews"}
     * )
     *
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Book ID",
     *     @OA\Schema(type="integer")
     * )
     *
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     @OA\Schema(type="integer", default=1)
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Number of reviews per page",
     *     @OA\Schema(type="integer", default=10)
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns reviews for the book",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="items", type="array", @OA\Items(ref=@Model(type=Review::class, groups={"review:list"}))),
     *        @OA\Property(property="pagination", type="object",
     *           @OA\Property(property="total", type="integer", example=12),
     *           @OA\Property(property="page", type="integer", example=1),
     *           @OA\Property(property="limit", type="integer", example=10),
     *           @OA\Property(property="pages", type="integer", example=2)
     *        )
     *     )
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Book not found"
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/books/{id}/reviews', name: 'api_book_reviews', methods: ['GET'])]
    public function getBookReviews(Book $book, Request $request): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);

        $reviews = $book->getReviews();
        $totalReviews = count($reviews);

        // Manual pagination for Collection
        $offset = ($page - 1) * $limit;
        $paginatedReviews = array_slice($reviews->toArray(), $offset, $limit);

        $data = [
            'items' => $paginatedReviews,
            'pagination' => [
                'total' => $totalReviews,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalReviews / $limit),
            ],
        ];

        return $this->json($data, 200, [], ['groups' => 'review:list']);
    }

    /**
     * List all reviews with pagination.
     *
     * @OA\Get(
     *     path="/api/reviews",
     *     summary="Get a list of reviews",
     *     tags={"Reviews"}
     * )
     *
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="Page number",
     *     @OA\Schema(type="integer", default=1)
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Number of reviews per page",
     *     @OA\Schema(type="integer", default=10)
     * )
     *
     * @OA\Parameter(
     *     name="sort",
     *     in="query",
     *     description="Sort by field (date_asc, date_desc, rating_asc, rating_desc)",
     *     @OA\Schema(type="string", default="date_desc")
     * )
     *
     * @OA\Parameter(
     *     name="search",
     *     in="query",
     *     description="Search term for book title, reviewer name, or review text",
     *     @OA\Schema(type="string")
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns a list of reviews",
     *     @OA\JsonContent(
     *        type="object",
     *        @OA\Property(property="items", type="array", @OA\Items(ref=@Model(type=Review::class, groups={"review:list"}))),
     *        @OA\Property(property="pagination", type="object",
     *           @OA\Property(property="total", type="integer", example=50),
     *           @OA\Property(property="page", type="integer", example=1),
     *           @OA\Property(property="limit", type="integer", example=10),
     *           @OA\Property(property="pages", type="integer", example=5)
     *        )
     *     )
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/reviews', name: 'api_reviews_index', methods: ['GET'])]
    public function listReviews(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $limit = $request->query->getInt('limit', 10);
        $sort = $request->query->get('sort');
        $search = $request->query->get('search');

        $reviewRepository = $entityManager->getRepository(Review::class);
        $queryBuilder = $reviewRepository->createQueryBuilder('r')
            ->leftJoin('r.book', 'b')
            ->leftJoin('r.reviewer', 'u');

        // Apply search if provided
        if ($search) {
            $queryBuilder
                ->where('b.title LIKE :search')
                ->orWhere('u.name LIKE :search')
                ->orWhere('r.reviewText LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // Apply sorting
        switch ($sort) {
            case 'date_asc':
                $queryBuilder->orderBy('r.createdAt', 'ASC');
                break;
            case 'date_desc':
                $queryBuilder->orderBy('r.createdAt', 'DESC');
                break;
            case 'rating_asc':
                $queryBuilder->orderBy('r.rating', 'ASC');
                break;
            case 'rating_desc':
                $queryBuilder->orderBy('r.rating', 'DESC');
                break;
            default:
                $queryBuilder->orderBy('r.createdAt', 'DESC');
        }

        // Count total for pagination
        $countQb = clone $queryBuilder;
        $totalReviews = $countQb->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();

        // Get paginated results
        $reviews = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = [
            'items' => $reviews,
            'pagination' => [
                'total' => $totalReviews,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($totalReviews / $limit),
            ],
        ];

        return $this->json($data, 200, [], ['groups' => 'review:list']);
    }

    /**
     * Get details of a specific review.
     *
     * @OA\Get(
     *     path="/api/reviews/{id}",
     *     summary="Get a specific review's details",
     *     tags={"Reviews"}
     * )
     *
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Review ID",
     *     @OA\Schema(type="integer")
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Returns review details",
     *     @OA\JsonContent(ref=@Model(type=Review::class, groups={"review:detail"}))
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Review not found"
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/reviews/{id}', name: 'api_reviews_show', methods: ['GET'])]
    public function getReview(Review $review): JsonResponse
    {
        return $this->json($review, 200, [], ['groups' => 'review:detail']);
    }

    /**
     * Create a new review for a book.
     *
     * @OA\Post(
     *     path="/api/reviews",
     *     summary="Create a new review",
     *     tags={"Reviews"}
     * )
     *
     * @OA\RequestBody(
     *     description="Review data",
     *     required=true,
     *     @OA\JsonContent(
     *         required={"bookId", "reviewText", "rating"},
     *         @OA\Property(property="bookId", type="integer", example=1),
     *         @OA\Property(property="reviewText", type="string", example="Great book, I really enjoyed it!"),
     *         @OA\Property(property="rating", type="integer", example=5)
     *     )
     * )
     *
     * @OA\Response(
     *     response=201,
     *     description="Review created",
     *     @OA\JsonContent(ref=@Model(type=Review::class, groups={"review:detail"}))
     * )
     *
     * @OA\Response(
     *     response=400,
     *     description="Invalid input"
     * )
     * @OA\Response(
     *     response=404,
     *     description="Book not found"
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/reviews', name: 'api_reviews_create', methods: ['POST'])]
    public function createReview(
        Request $request,
        EntityManagerInterface $entityManager,
        ApiRequestValidator $requestValidator,
    ): JsonResponse {
        // Validate request data
        $errors = $requestValidator->validateReviewInput($request);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        // Find the book
        $book = $entityManager->getRepository(Book::class)->find($data['bookId']);
        if (!$book) {
            return $this->json(['error' => 'Book not found'], Response::HTTP_NOT_FOUND);
        }

        // Create the review
        $review = new Review();
        $review->setBook($book);
        $review->setReviewer($this->getUser());
        $review->setReviewText($data['reviewText']);
        $review->setRating($data['rating']);
        $review->setCreatedAt(new \DateTime());

        $entityManager->persist($review);
        $entityManager->flush();

        return $this->json(
            $review,
            Response::HTTP_CREATED,
            [],
            ['groups' => 'review:detail']
        );
    }

    /**
     * Update an existing review.
     *
     * @OA\Put(
     *     path="/api/reviews/{id}",
     *     summary="Update an existing review",
     *     tags={"Reviews"}
     * )
     *
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Review ID",
     *     @OA\Schema(type="integer")
     * )
     *
     * @OA\RequestBody(
     *     description="Review data",
     *     required=true,
     *     @OA\JsonContent(
     *         required={"reviewText", "rating"},
     *         @OA\Property(property="reviewText", type="string", example="Updated review text"),
     *         @OA\Property(property="rating", type="integer", example=4)
     *     )
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Review updated",
     *     @OA\JsonContent(ref=@Model(type=Review::class, groups={"review:detail"}))
     * )
     *
     * @OA\Response(
     *     response=400,
     *     description="Invalid input"
     * )
     * @OA\Response(
     *     response=403,
     *     description="Forbidden - not review owner"
     * )
     * @OA\Response(
     *     response=404,
     *     description="Review not found"
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/reviews/{id}', name: 'api_reviews_update', methods: ['PUT'])]
    public function updateReview(
        Review $review,
        Request $request,
        EntityManagerInterface $entityManager,
        ApiRequestValidator $requestValidator,
    ): JsonResponse {
        // Check if user is the owner of this review
        if (!$review->isOwnedBy($this->getUser()) && !$this->isGranted('ROLE_MANAGER')) {
            return $this->json(['error' => 'You cannot edit this review'], Response::HTTP_FORBIDDEN);
        }

        // Validate request data
        $errors = $requestValidator->validateReviewInput($request);
        if (!empty($errors)) {
            return $this->json(['errors' => $errors], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        // Update the review
        $review->setReviewText($data['reviewText']);
        $review->setRating($data['rating']);
        $review->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        return $this->json(
            $review,
            Response::HTTP_OK,
            [],
            ['groups' => 'review:detail']
        );
    }

    /**
     * Delete a review.
     *
     * @OA\Delete(
     *     path="/api/reviews/{id}",
     *     summary="Delete a review",
     *     tags={"Reviews"}
     * )
     *
     * @OA\Parameter(
     *     name="id",
     *     in="path",
     *     required=true,
     *     description="Review ID",
     *     @OA\Schema(type="integer")
     * )
     *
     * @OA\Response(
     *     response=200,
     *     description="Review deleted successfully",
     *     @OA\JsonContent(
     *         @OA\Property(property="message", type="string", example="Review deleted successfully")
     *     )
     * )
     * @OA\Response(
     *     response=403,
     *     description="Forbidden - not review owner"
     * )
     * @OA\Response(
     *     response=404,
     *     description="Review not found"
     * )
     *
     * @Security(name="Bearer")
     */
    #[Route('/reviews/{id}', name: 'api_reviews_delete', methods: ['DELETE'])]
    public function deleteReview(Review $review, EntityManagerInterface $entityManager): JsonResponse
    {
        // Check if user is the owner of this review or has manager role
        if (!$review->isOwnedBy($this->getUser()) && !$this->isGranted('ROLE_MANAGER')) {
            return $this->json(['error' => 'You cannot delete this review'], Response::HTTP_FORBIDDEN);
        }

        $entityManager->remove($review);
        $entityManager->flush();

        return $this->json(['message' => 'Review deleted successfully'], Response::HTTP_OK);
    }
}
