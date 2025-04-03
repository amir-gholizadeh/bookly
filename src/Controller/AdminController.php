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
    public function userList(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $entityManager->getRepository(User::class)->findAll();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
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
}
