<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/manager')]
class ManagerController extends AbstractController
{
    #[Route('/dashboard', name: 'manager_dashboard')]
    public function dashboard(BookRepository $bookRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        // Get all books for managers to review
        $books = $bookRepository->findAll();

        return $this->render('manager/dashboard.html.twig', [
            'books' => $books,
        ]);
    }

}
