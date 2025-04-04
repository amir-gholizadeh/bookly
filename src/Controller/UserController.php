<?php

namespace App\Controller;

use App\Entity\Review;
use App\Entity\User;
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
        $sort = $request->query->get('sort');
        $search = $request->query->get('search');
        $limit = 10; // Number of books per page

        $paginator = $bookRepository->findPaginatedBooks($page, $limit, $sort, $search);

        return $this->render('main/index.html.twig', [
            'books' => $paginator,
            'currentPage' => $page,
            'totalPages' => ceil(count($paginator) / $limit),
        ]);
    }

    #[Route('/search-suggestions', name: 'search_suggestions')]
    public function searchSuggestions(Request $request, BookRepository $bookRepository): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $suggestions = $bookRepository->findSearchSuggestions($query);

        return $this->json($suggestions);
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
            if ($this->isGranted('ROLE_ADMIN') && $form->has('roles')) {
                $role = $form->get('roles')->getData();
                if ('ROLE_MANAGER' === $role) {
                    $user->setRoles(['ROLE_MANAGER']);
                }
            } else {
                // Default role for regular registration
                $user->setRoles(['ROLE_USER']);
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

    #[Route('/reviews', name: 'all_reviews')]
    public function allReviews(Request $request, EntityManagerInterface $entityManager): Response
    {
        $page = $request->query->getInt('page', 1);
        $sort = $request->query->get('sort');
        $search = $request->query->get('search');
        $limit = 10; // Number of reviews per page

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
            case 'book_asc':
                $queryBuilder->orderBy('b.title', 'ASC');
                break;
            case 'book_desc':
                $queryBuilder->orderBy('b.title', 'DESC');
                break;
            default:
                $queryBuilder->orderBy('r.createdAt', 'DESC'); // Default sort by most recent
        }

        $totalReviews = count($queryBuilder->getQuery()->getResult());
        $totalPages = ceil($totalReviews / $limit);

        $reviews = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('main/reviews.html.twig', [
            'reviews' => $reviews,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/review-search-suggestions', name: 'review_search_suggestions')]
    public function reviewSearchSuggestions(Request $request, EntityManagerInterface $entityManager): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $reviewRepository = $entityManager->getRepository(Review::class);
        $queryBuilder = $reviewRepository->createQueryBuilder('r')
            ->select('DISTINCT b.title', 'u.name', 'r.reviewText')
            ->leftJoin('r.book', 'b')
            ->leftJoin('r.reviewer', 'u')
            ->where('b.title LIKE :query')
            ->orWhere('u.name LIKE :query')
            ->orWhere('r.reviewText LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->setMaxResults(10);

        $results = $queryBuilder->getQuery()->getResult();

        // Format results for autocomplete
        $suggestions = [];
        foreach ($results as $result) {
            if (false !== stripos($result['title'], $query) && !in_array($result['title'], array_column($suggestions, 'value'))) {
                $suggestions[] = ['value' => $result['title'], 'type' => 'book'];
            }
            if (false !== stripos($result['name'], $query) && !in_array($result['name'], array_column($suggestions, 'value'))) {
                $suggestions[] = ['value' => $result['name'], 'type' => 'reviewer'];
            }
            // For review text, we'll extract a small snippet containing the search term
            if (false !== stripos($result['reviewText'], $query)) {
                $position = stripos($result['reviewText'], $query);
                $start = max(0, $position - 15);
                $length = min(strlen($result['reviewText']) - $start, 50);
                $snippet = '...'.substr($result['reviewText'], $start, $length).'...';

                if (!in_array($snippet, array_column($suggestions, 'value'))) {
                    $suggestions[] = ['value' => $snippet, 'type' => 'content'];
                }
            }
        }

        return $this->json(array_slice($suggestions, 0, 5));
    }
}
