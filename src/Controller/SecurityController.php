<?php

namespace App\Controller;

use App\Entity\PlayerProfile;
use App\Entity\User;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $authenticationError = $authenticationUtils->getLastAuthenticationError();

        return $this->render('security/login.html.twig', [
            'lastUsername' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationError instanceof CustomUserMessageAccountStatusException
                ? $authenticationError->getMessageKey()
                : ($authenticationError !== null ? 'Email ou mot de passe incorrect.' : null),
        ]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        EmailVerifier $emailVerifier,
    ): Response {
        $email = '';
        $displayName = '';
        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $email = strtolower(trim((string) $request->request->get('email', '')));
            $displayName = trim((string) $request->request->get('display_name', ''));
            $password = $request->request->get('password');
            $passwordConfirmation = $request->request->get('password_confirmation');
            $displayNameLength = preg_match_all('/./us', $displayName);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 180) {
                $errors[] = 'Saisissez une adresse email valide.';
            } elseif ($entityManager->getRepository(User::class)->findOneBy(['email' => $email]) instanceof User) {
                $errors[] = 'Cette adresse email possède déjà un compte.';
            }

            if ($displayName === '' || $displayNameLength === false || $displayNameLength > 80) {
                $errors[] = 'Le nom affiché doit contenir entre 1 et 80 caractères.';
            }

            if (!is_string($password) || strlen($password) < 12 || strlen($password) > 4096) {
                $errors[] = 'Le mot de passe doit contenir au moins 12 caractères.';
            } elseif (!is_string($passwordConfirmation) || !hash_equals($password, $passwordConfirmation)) {
                $errors[] = 'Les deux mots de passe ne correspondent pas.';
            }

            if ($errors === []) {
                $user = new User($email);
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $profile = new PlayerProfile($user, $displayName);

                $entityManager->persist($user);
                $entityManager->persist($profile);
                $entityManager->flush();

                $emailVerifier->sendConfirmation($user);
                $this->addFlash('registration_success', 'Un lien de confirmation vient de vous être envoyé par email.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'email' => $email,
            'displayName' => $displayName,
            'errors' => $errors,
        ], new Response(status: $errors === [] ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/verification-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(
        Request $request,
        EntityManagerInterface $entityManager,
        EmailVerifier $emailVerifier,
    ): Response {
        $userId = filter_var($request->query->get('id'), FILTER_VALIDATE_INT);
        $user = is_int($userId) ? $entityManager->getRepository(User::class)->find($userId) : null;

        if (!$user instanceof User) {
            throw $this->createNotFoundException();
        }

        if (!$user->isVerified()) {
            try {
                $emailVerifier->verify($request, $user);
            } catch (VerifyEmailExceptionInterface) {
                $this->addFlash('auth_error', 'Le lien de confirmation est invalide ou expiré.');

                return $this->redirectToRoute('app_login');
            }
        }

        $this->addFlash('registration_success', 'Adresse email confirmée. Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('The firewall handles logout requests.');
    }
}
