<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationConfirmationController extends AbstractController
{
    #[Route('/registration/confirmation', name: 'app_registration_confirmation')]
    public function index(): Response
    {
        return $this->render('registration_confirmation/index.html.twig');
        ;
    }
}
