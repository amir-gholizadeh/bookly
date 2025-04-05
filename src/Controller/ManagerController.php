<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/manager')]
class ManagerController extends AbstractController
{
    #[Route('/dashboard', name: 'manager_dashboard')]
    public function dashboard(BookRepository $bookRepository, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        // Get search parameter and pagination details
        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $sort = $request->query->get('sort');
        $limit = 15; // Number of books per page

        // Get paginated books based on search or sort parameters
        $paginator = $bookRepository->findPaginatedBooks($page, $limit, $sort, $search);

        return $this->render('manager/dashboard.html.twig', [
            'books' => $paginator,
            'currentPage' => $page,
            'totalPages' => ceil(count($paginator) / $limit),
            'search' => $search,
        ]);
    }

}
