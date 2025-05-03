<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Livre;
use App\Form\CommandeType;
use App\Repository\LivreRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[Route('/client')]
final class ClientController extends AbstractController
{
    #[Route('/', name: 'app_client', methods: ['GET', 'POST'])]
    public function index(
        LivreRepository $livreRepository,
        PaginatorInterface $paginator,
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $cart = $session->get('cart', []);

        if ($request->isMethod('POST') && $request->request->has('ajouter_panier')) {
            $livreId = $request->request->getInt('livreID');
            $livre = $livreRepository->find($livreId);
            if (!$livre) {
                throw new NotFoundHttpException('Livre non trouvé.');
            }
            if (!$livre->isAvailable()) {
                $this->addFlash('error', 'Livre non disponible en stock.');
                return $this->redirectToRoute('app_client');
            }
            if ($this->isCsrfTokenValid('add_to_cart' . $livreId, $request->request->get('_token'))) {
                $cart[$livreId] = ($cart[$livreId] ?? 0) + 1;
                if ($cart[$livreId] > $livre->getStock()) {
                    $this->addFlash('error', 'Quantité demandée dépasse le stock.');
                    unset($cart[$livreId]);
                } else {
                    $session->set('cart', $cart);
                    $this->addFlash('success', 'Livre ajouté au panier avec succès.');
                }
                return $this->redirectToRoute('app_client');
            }
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        $livres = $paginator->paginate(
            $livreRepository->findAll(),
            $request->query->getInt('page', 1),
            9
        );

        return $this->render('client/index.html.twig', [
            'livres' => $livres,
        ]);
    }

    #[Route('/livre/{id}', name: 'app_show_client', methods: ['GET', 'POST'])]
    public function show(
        ?Livre $livre,
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        if (!$livre) {
            throw new NotFoundHttpException('Livre non trouvé.');
        }

        $cart = $session->get('cart', []);

        if ($request->isMethod('POST') && $request->request->has('ajouter_panier')) {
            if (!$livre->isAvailable()) {
                $this->addFlash('error', 'Livre non disponible en stock.');
                return $this->redirectToRoute('app_show_client', ['id' => $livre->getId()]);
            }
            if ($this->isCsrfTokenValid('add_to_cart' . $livre->getId(), $request->request->get('_token'))) {
                $cart[$livre->getId()] = ($cart[$livre->getId()] ?? 0) + 1;
                if ($cart[$livre->getId()] > $livre->getStock()) {
                    $this->addFlash('error', 'Quantité demandée dépasse le stock.');
                    unset($cart[$livre->getId()]);
                } else {
                    $session->set('cart', $cart);
                    $this->addFlash('success', 'Livre ajouté au panier avec succès.');
                }
                return $this->redirectToRoute('app_client_panier');
            }
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->render('client/details.html.twig', [
            'livre' => $livre,
        ]);
    }

    #[Route('/panier', name: 'app_client_panier', methods: ['GET', 'POST'])]
    public function voirPanier(
        LivreRepository $livreRepository,
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $cart = $session->get('cart', []);
        $livres = [];
        $total = 0;

        foreach ($cart as $livreId => $quantite) {
            $livre = $livreRepository->find($livreId);
            if ($livre) {
                $livres[] = [
                    'livre' => $livre,
                    'quantite' => $quantite,
                    'total' => $livre->getPrix() * $quantite,
                ];
                $total += $livre->getPrix() * $quantite;
            }
        }

        if ($request->isMethod('POST')) {
            $livreId = $request->request->getInt('livreId');
            $action = $request->request->get('action');
            if (isset($cart[$livreId]) && $this->isCsrfTokenValid('panier_action' . $livreId, $request->request->get('_token'))) {
                $livre = $livreRepository->find($livreId);
                if ($action === 'update') {
                    $quantite = $request->request->getInt('quantite');
                    if ($quantite <= 0) {
                        unset($cart[$livreId]);
                    } elseif ($livre && $quantite <= $livre->getStock()) {
                        $cart[$livreId] = $quantite;
                    } else {
                        $this->addFlash('error', 'Quantité demandée dépasse le stock disponible.');
                    }
                } elseif ($action === 'remove') {
                    unset($cart[$livreId]);
                }
                $session->set('cart', $cart);
                $this->addFlash('success', 'Panier mis à jour.');
                return $this->redirectToRoute('app_client_panier');
            }
            $this->addFlash('error', 'Action invalide.');
        }

        return $this->render('client/panier.html.twig', [
            'livres' => $livres,
            'total' => $total,
        ]);
    }

    #[Route('/checkout', name: 'app_client_checkout', methods: ['GET', 'POST'])]
    public function checkout(
        Request $request,
        SessionInterface $session,
        EntityManagerInterface $em,
        LivreRepository $livreRepository,
        MailerInterface $mailer
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $cart = $session->get('cart', []);

        if (empty($cart)) {
            $this->addFlash('error', 'Votre panier est vide.');
            return $this->redirectToRoute('app_client_panier');
        }

        $commande = new Commande();
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $items = [];
            $quantiteTotal = 0;
            $total = 0;

            foreach ($cart as $livreId => $quantite) {
                $livre = $livreRepository->find($livreId);
                if ($livre && $quantite <= $livre->getStock()) {
                    $items[] = [
                        'livre_id' => $livreId,
                        'quantite' => $quantite,
                    ];
                    $quantiteTotal += $quantite;
                    $total += $livre->getPrix() * $quantite;
                    $livre->decreaseStock($quantite);
                    $em->persist($livre);
                } else {
                    $this->addFlash('error', "Stock insuffisant pour {$livre->getTitre()}.");
                    return $this->redirectToRoute('app_client_checkout');
                }
            }

            $commande->setClient($user)
                ->setItems($items)
                ->setQuantiteTotal($quantiteTotal)
                ->setTotal((string)$total)
                ->setAdresse($form->get('adresse')->getData())
                ->setMethodePaiement($form->get('methodePaiement')->getData())
                ->setStatut('Not Delivered')
                ->setDateCommande(new \DateTime())
                ->setNumeroCommande(uniqid('CMD-'));

            $em->persist($commande);
            $em->flush();

            $session->remove('cart');


            $this->addFlash('success', 'Commande passée avec succès.');
            return $this->render('client/confirmation.html.twig', [
                'commande' => $commande,
            ]);
        }

        return $this->render('client/checkout.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/commandes', name: 'app_client_commandes', methods: ['GET'])]
    public function voirCommandes(CommandeRepository $commandeRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $user = $this->getUser();
        $commandes = $commandeRepository->findBy(['client' => $user], ['dateCommande' => 'DESC']);

        return $this->render('client/commandes.html.twig', [
            'commandes' => $commandes,
        ]);
    }
}
