<?php

namespace App\Controller;
use DateTime;
use App\Entity\Livre;
use App\Form\LivreType;
use App\Repository\LivreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/livre')]
final class LivreController extends AbstractController
{
    #[Route(name: 'app_livre_index', methods: ['GET'])]
    public function index(LivreRepository $rep ,EntityManagerInterface $em, PaginatorInterface $paginator, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $livres = $paginator->paginate(
            $rep->findAll(),
            $request->query->getInt('page', 1), /* page number */
            10 /* limit per page */
        );

        return $this->render('livre/all.html.twig', ['livres' => $livres],);
    }

    #[Route('/new', name: 'app_livre_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {   $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $livre = new Livre();
        $form = $this->createForm(LivreType::class, $livre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($livre);
            $entityManager->flush();

            return $this->redirectToRoute('app_livre_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('livre/new.html.twig', [
            'livre' => $livre,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_livre_show', methods: ['GET'])]
    public function show(Livre $livre): Response
    {
        return $this->render('livre/details.html.twig', [
            'livre' => $livre,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_livre_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Livre $livre, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $form = $this->createForm(LivreType::class, $livre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_livre_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('livre/edit.html.twig', [
            'livre' => $livre,
            'form' => $form,
        ]);
    }
    #[Route('/{id}', name: 'app_livre_delete', methods: ['POST'])]
    public function delete(Request $request, Livre $livre, EntityManagerInterface $entityManager): Response
{       $this->denyAccessUnlessGranted('ROLE_ADMIN');
    if ($this->isCsrfTokenValid('delete'.$livre->getId(), $request->getPayload()->getString('_token'))) {
        $entityManager->remove($livre);
        $entityManager->flush();
    }

    return $this->redirectToRoute('app_livre_index', [], Response::HTTP_SEE_OTHER);
}
}
