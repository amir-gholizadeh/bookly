<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use App\Form\BookFormType;
use App\Form\LoginFormType;
use App\Form\RegistrationFormType;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Liip\ImagineBundle\Binary\Loader\LoaderInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Filter\FilterManager;
use Liip\ImagineBundle\Model\Binary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\String\Slugger\SluggerInterface;

class UserController extends AbstractController
{
    #[Route('/', name: 'main')]
    public function index(BookRepository $bookRepository, Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 10; // Number of books per page

        $paginator = $bookRepository->findPaginatedBooks($page, $limit);

        return $this->render('main/index.html.twig', [
            'books' => $paginator,
            'currentPage' => $page,
            'totalPages' => ceil(count($paginator) / $limit),
        ]);
    }

    #[Route('/book/new', name: 'book_new')]
    public function newBook(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, FilterManager $filterManager): Response
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

    #[Route('/book/{id}', name: 'book_show')]
    public function showBook(Book $book): Response
    {
        return $this->render('book/show.html.twig', [
            'book' => $book,
        ]);
    }


    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager, SluggerInterface $slugger, Security $security, CacheManager $cacheManager, FilterManager $filterManager, LoaderInterface $loader): Response
    {
        if ($this->getUser()) {
            $this->addFlash('info', 'Hi '.$this->getUser()->getName().'. You are already logged in. Please logout first.');

            return $this->redirectToRoute('main');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $profilePictureFile = $form->get('profilePicturePath')->getData();
            if ($profilePictureFile) {
                $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$profilePictureFile->guessExtension();

                try {
                    $profilePictureFile->move(
                        $this->getParameter('profile_pictures_directory'),
                        $newFilename
                    );

                    // Load the image as a binary
                    $file = new File($this->getParameter('profile_pictures_directory').'/'.$newFilename);
                    $binary = new Binary(file_get_contents($file->getPathname()), $file->getMimeType(), $file->guessExtension());

                    // Apply the thumbnail filter
                    $filteredBinary = $filterManager->applyFilter($binary, 'thumbnail');

                    // Save the filtered image
                    file_put_contents($file->getPathname(), $filteredBinary->getContent());
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload profile picture.');
                }

                $user->setProfilePicturePath($newFilename);
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Registration successful! Welcome!');

            // Log in the user manually
            $security->login($user);

            // Redirect to the main page
            return $this->redirectToRoute('main');
        }

        return $this->render('main/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/login', name: 'app_login')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            $this->addFlash('info', 'Hi '.$this->getUser()->getName().'. You are already logged in. Please logout first.');

            return $this->redirectToRoute('main');
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);

        return $this->render('main/login.html.twig', [
            'loginForm' => $form->createView(),
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Symfony will handle the logout logic
    }

    // This route is for testing session management
    #[Route('/check-session', name: 'check_session')]
    public function checkSession(Request $request): Response
    {
        // Start the session if it is not already started
        $session = $request->getSession();
        if (!$session->isStarted()) {
            $session->start();
        }

        // Set a test session variable
        $session->set('test', 'Session is working!');

        // Retrieve the test session variable
        $testValue = $session->get('test');

        return new Response('Session test value: '.$testValue);
    }
}
