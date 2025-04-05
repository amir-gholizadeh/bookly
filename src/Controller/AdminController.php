<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserRoleFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('/users', name: 'admin_users')]
    public function userList(EntityManagerInterface $entityManager, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get search parameter and pagination details
        $search = $request->query->get('search');
        $page = $request->query->getInt('page', 1);
        $sort = $request->query->get('sort');
        $limit = 20; // Number of users per page

        $repository = $entityManager->getRepository(User::class);
        $queryBuilder = $repository->createQueryBuilder('u');

        // Apply search if provided
        if ($search) {
            $queryBuilder
                ->where('u.name LIKE :search')
                ->orWhere('u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // Apply sorting
        switch ($sort) {
            case 'name_asc':
                $queryBuilder->orderBy('u.name', 'ASC');
                break;
            case 'name_desc':
                $queryBuilder->orderBy('u.name', 'DESC');
                break;
            case 'email_asc':
                $queryBuilder->orderBy('u.email', 'ASC');
                break;
            case 'email_desc':
                $queryBuilder->orderBy('u.email', 'DESC');
                break;
            default:
                // Default sorting by id (most recent first)
                $queryBuilder->orderBy('u.id', 'DESC');
        }

        // Count total results for pagination
        $countQuery = clone $queryBuilder;
        $totalUsers = $countQuery->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();
        $totalPages = ceil($totalUsers / $limit);

        // Get paginated results
        $users = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'currentPage' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/user/{id}/edit', name: 'admin_user_edit')]
    public function editUser(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(UserRoleFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'User roles updated');

            return $this->redirectToRoute('admin_users');
        }

        return $this->render('admin/edit_user.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/user/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function deleteUser(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            // Don't allow deleting your own account
            if ($user === $this->getUser()) {
                $this->addFlash('error', 'You cannot delete your own account.');

                return $this->redirectToRoute('admin_users');
            }

            // Delete profile picture if it exists
            if ($user->getProfilePicturePath()) {
                $picturePath = $this->getParameter('profile_pictures_directory').'/'.$user->getProfilePicturePath();
                if (file_exists($picturePath)) {
                    unlink($picturePath);
                }
            }

            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'User deleted successfully.');
        }

        return $this->redirectToRoute('admin_users');
    }

    #[Route('/user-search-suggestions', name: 'admin_user_search_suggestions')]
    public function userSearchSuggestions(Request $request, EntityManagerInterface $entityManager): Response
    {
        $query = $request->query->get('q', '');

        if (strlen($query) < 2) {
            return $this->json([]);
        }

        $repository = $entityManager->getRepository(User::class);
        $queryBuilder = $repository->createQueryBuilder('u')
            ->where('u.name LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->setMaxResults(5);

        $users = $queryBuilder->getQuery()->getResult();

        // Format results for autocomplete
        $suggestions = [];
        foreach ($users as $user) {
            if (false !== stripos($user->getName(), $query) && !in_array($user->getName(), array_column($suggestions, 'value'))) {
                $suggestions[] = ['value' => $user->getName(), 'type' => 'name'];
            }
            if (false !== stripos($user->getEmail(), $query) && !in_array($user->getEmail(), array_column($suggestions, 'value'))) {
                $suggestions[] = ['value' => $user->getEmail(), 'type' => 'email'];
            }

            // Add role suggestions if the query matches a role
            foreach ($user->getRoles() as $role) {
                $displayRole = str_replace('ROLE_', '', $role);
                if (false !== stripos($displayRole, $query) && !in_array($displayRole, array_column($suggestions, 'value'))) {
                    $suggestions[] = ['value' => $displayRole, 'type' => 'role'];
                }
            }
        }

        return $this->json(array_slice($suggestions, 0, 5));
    }
}
