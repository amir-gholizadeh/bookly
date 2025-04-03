<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\Review;
use App\Form\BookFormType;
use App\Form\ReviewFormType;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/book')]
class BookController extends AbstractController
{
    // Show a book - available to all users
    #[Route('/{id}', name: 'book_show', requirements: ['id' => '\d+'])]
    public function show(Book $book, Request $request): Response
    {
        // Create review form if user is logged in
        $reviewForm = null;
        if ($this->getUser()) {
            $review = new Review();
            $reviewForm = $this->createForm(ReviewFormType::class, $review, [
                'action' => $this->generateUrl('book_add_review', ['id' => $book->getId()]),
            ]);
        }

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'reviewForm' => $reviewForm ? $reviewForm->createView() : null,
        ]);
    }

    #[Route('/{id}/review', name: 'book_add_review', methods: ['POST'])]
    public function addReview(Book $book, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $review = new Review();
        $review->setBook($book);
        $review->setReviewer($this->getUser());
        $review->setCreatedAt(new \DateTime());

        $form = $this->createForm(ReviewFormType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($review);
            $entityManager->flush();

            $this->addFlash('success', 'Your review has been added!');
        }

        return $this->redirectToRoute('book_show', ['id' => $book->getId()]);
    }

    #[Route('/review/{id}/edit', name: 'review_edit')]
    public function editReview(Review $review, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Check if user is the owner of this review
        if (!$review->isOwnedBy($this->getUser()) && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('You cannot edit this review');
        }

        $form = $this->createForm(ReviewFormType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Your review has been updated!');

            return $this->redirectToRoute('book_show', ['id' => $review->getBook()->getId()]);
        }

        return $this->render('book/edit_review.html.twig', [
            'reviewForm' => $form->createView(),
            'review' => $review,
            'book' => $review->getBook(),
        ]);
    }

    #[Route('/review/{id}/delete', name: 'review_delete', methods: ['POST'])]
    public function deleteReview(Review $review, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Check if user is the owner of this review or a manager
        if (!$review->isOwnedBy($this->getUser()) && !$this->isGranted('ROLE_MANAGER')) {
            throw $this->createAccessDeniedException('You cannot delete this review');
        }

        $bookId = $review->getBook()->getId();

        if ($this->isCsrfTokenValid('delete'.$review->getId(), $request->request->get('_token'))) {
            $entityManager->remove($review);
            $entityManager->flush();
            $this->addFlash('success', 'Review deleted successfully!');
        }

        return $this->redirectToRoute('book_show', ['id' => $bookId]);
    }

    // Create a new book - available to all logged-in users
    #[Route('/new', name: 'book_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FilterManager $filterManager): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $book = new Book();
        $form = $this->createForm(BookFormType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverImageFile = $form->get('coverImagePath')->getData();
            if ($coverImageFile) {
                $originalFilename = pathinfo($coverImageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$coverImageFile->guessExtension();

                try {
                    $coverImageFile->move(
                        $this->getParameter('cover_images_directory'),
                        $newFilename
                    );

                    // Load the image as a binary
                    $file = new File($this->getParameter('cover_images_directory').'/'.$newFilename);
                    $binary = new Binary(file_get_contents($file->getPathname()), $file->getMimeType(), $file->guessExtension());

                    // Apply the resize filter
                    $filteredBinary = $filterManager->applyFilter($binary, 'resize_600x900');

                    // Save the filtered image
                    file_put_contents($file->getPathname(), $filteredBinary->getContent());
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload cover image.');
                }

                $book->setCoverImagePath($newFilename);
            }

            $entityManager->persist($book);
            $entityManager->flush();

            $this->addFlash('success', 'Book added successfully!');

            return $this->redirectToRoute('main');
        }

        return $this->render('book/new.html.twig', [
            'bookForm' => $form->createView(),
        ]);
    }

    // Edit a book - restricted to managers and admins
    #[Route('/{id}/edit', name: 'book_edit')]
    public function edit(Book $book, Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FilterManager $filterManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        $form = $this->createForm(BookFormType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverImageFile = $form->get('coverImagePath')->getData();
            if ($coverImageFile) {
                $originalFilename = pathinfo($coverImageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$coverImageFile->guessExtension();

                try {
                    $coverImageFile->move(
                        $this->getParameter('cover_images_directory'),
                        $newFilename
                    );

                    // Load the image as a binary
                    $file = new File($this->getParameter('cover_images_directory').'/'.$newFilename);
                    $binary = new Binary(file_get_contents($file->getPathname()), $file->getMimeType(), $file->guessExtension());

                    // Apply the resize filter
                    $filteredBinary = $filterManager->applyFilter($binary, 'resize_600x900');

                    // Save the filtered image
                    file_put_contents($file->getPathname(), $filteredBinary->getContent());

                    // Remove old image if it exists
                    if ($book->getCoverImagePath()) {
                        $oldImagePath = $this->getParameter('cover_images_directory').'/'.$book->getCoverImagePath();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }

                    $book->setCoverImagePath($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload cover image.');
                }
            }

            $entityManager->flush();
            $this->addFlash('success', 'Book updated successfully!');

            return $this->redirectToRoute('book_show', ['id' => $book->getId()]);
        }

        return $this->render('book/edit.html.twig', [
            'book' => $book,
            'bookForm' => $form->createView(),
        ]);
    }

    // Delete a book - restricted to managers and admins
    #[Route('/{id}/delete', name: 'book_delete', methods: ['POST'])]
    public function delete(Request $request, Book $book, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

        if ($this->isCsrfTokenValid('delete'.$book->getId(), $request->request->get('_token'))) {
            // Remove cover image file if it exists
            if ($book->getCoverImagePath()) {
                $imagePath = $this->getParameter('cover_images_directory').'/'.$book->getCoverImagePath();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $entityManager->remove($book);
            $entityManager->flush();
            $this->addFlash('success', 'Book deleted successfully!');
        }

        return $this->redirectToRoute('main');
    }
}
