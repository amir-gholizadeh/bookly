<?php

namespace App\Controller;

use App\Entity\Book;
use App\Form\BookFormType;
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
    public function show(Book $book): Response
    {
        return $this->render('book/show.html.twig', [
            'book' => $book,
        ]);
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
