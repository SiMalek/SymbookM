<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            $user->setRoles(['ROLE_USER']);
            $entityManager->persist($user);
            $entityManager->flush();

            try {
                $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                    (new TemplatedEmail())
                        ->from(new Address('ahmedbessrour81@gmail.com', 'ahmed'))
                        ->to((string) $user->getEmail())
                        ->subject('Please Confirm your Email')
                        ->htmlTemplate('registration/confirmation_email.html.twig')
                );
                $this->addFlash('success', 'Verification email sent. Please check your inbox.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to send verification email: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_registration_confirmation');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

   #[Route('/verify/email', name: 'app_verify_email')]
public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
{
    $id = $request->query->get('id'); // Get ID from signed URL
    if (!$id) {
        $this->addFlash('verify_email_error', 'Invalid verification link.');
        return $this->redirectToRoute('app_register');
    }

    $user = $entityManager->getRepository(User::class)->find($id);
    if (!$user) {
        $this->addFlash('verify_email_error', 'User not found.');
        return $this->redirectToRoute('app_register');
    }

    try {
        $this->emailVerifier->handleEmailConfirmation($request, $user);
    } catch (VerifyEmailExceptionInterface $exception) {
        dump($exception->getReason(), $exception->getMessage()); // Debug
        $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
        return $this->redirectToRoute('app_register');
    }
    dump($request->getSession()->getId());
    $this->addFlash('success', 'Your email address has been verified.');
    return $this->redirectToRoute('app_client');
}
}