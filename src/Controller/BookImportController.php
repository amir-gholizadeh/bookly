<?php

namespace App\Controller;

use App\Entity\Book;
use App\Service\GoogleBooksService;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BookImportController extends AbstractController
{
    private LoggerInterface $logger;
    private FilterManager $filterManager;

    public function __construct(LoggerInterface $logger, FilterManager $filterManager)
    {
        $this->logger = $logger;
        $this->filterManager = $filterManager;
    }

    #[Route('/manager/book-import', name: 'book_import')]
    public function import(Request $request, GoogleBooksService $googleBooksService): Response
    {
        $searchQuery = $request->query->get('q');
        $searchResults = [];

        if ($searchQuery) {
            $searchResults = $googleBooksService->searchBooks($searchQuery);
        }

        return $this->render('book/import.html.twig', [
            'searchQuery' => $searchQuery,
            'searchResults' => $searchResults,
        ]);
    }

    #[Route('/manager/book-import/{googleBooksId}', name: 'book_import_details')]
    public function importDetails(string $googleBooksId, GoogleBooksService $googleBooksService): Response
    {
        $bookDetails = $googleBooksService->getBookDetails($googleBooksId);

        if (!$bookDetails) {
            $this->addFlash('error', 'Book details could not be found.');

            return $this->redirectToRoute('book_import');
        }

        return $this->render('book/import_details.html.twig', [
            'bookDetails' => $bookDetails,
        ]);
    }

    #[Route('/manager/book-import-save', name: 'book_import_save', methods: ['POST'])]
    public function importSave(
        Request $request,
        GoogleBooksService $googleBooksService,
        EntityManagerInterface $entityManager,
    ): Response {
        $googleBooksId = $request->request->get('google_books_id');

        if (!$googleBooksId) {
            $this->addFlash('error', 'Invalid book ID.');

            return $this->redirectToRoute('book_import');
        }

        $bookDetails = $googleBooksService->getBookDetails($googleBooksId);

        if (!$bookDetails) {
            $this->addFlash('error', 'Book details could not be found.');

            return $this->redirectToRoute('book_import');
        }

        $book = $googleBooksService->createBookFromApiData($bookDetails);

        // If the book has a cover image, download it
        if (!empty($bookDetails['imageLinks']['thumbnail'])) {
            $coverImagePath = $this->downloadCoverImage($bookDetails['imageLinks']['thumbnail'], $book->getTitle());
            if ($coverImagePath) {
                $book->setCoverImagePath($coverImagePath);
            }
        }

        $entityManager->persist($book);
        $entityManager->flush();

        $this->addFlash('success', 'Book "'.$book->getTitle().'" has been imported successfully.');

        return $this->redirectToRoute('book_show', ['id' => $book->getId()]);
    }

    /**
     * Download a cover image from URL and save it to the cover images directory.
     */
    private function downloadCoverImage(string $imageUrl, string $title): ?string
    {
        try {
            // Create a clean filename based on the book title
            $filename = uniqid().'-'.strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $title)).'.jpg';
            $targetDir = $this->getParameter('cover_images_directory');
            $targetPath = $targetDir.'/'.$filename;

            // Ensure directory exists
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }

            // Download the image
            $imageContent = file_get_contents($imageUrl);
            file_put_contents($targetPath, $imageContent);

            // Resize the image to standard dimensions
            try {
                $imagine = new \Imagine\Gd\Imagine();
                $size = new \Imagine\Image\Box(600, 900);

                $imagine->open($targetPath)
                    ->resize($size)
                    ->save($targetPath);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to resize cover image: '.$e->getMessage());
                // Continue even if resize fails
            }

            return $filename;
        } catch (\Exception $e) {
            $this->logger->error('Failed to download cover image: '.$e->getMessage());

            return null;
        }
    }
}
