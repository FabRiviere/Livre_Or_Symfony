<?php

namespace App\Controller;

use App\Service\SendMailService;
use App\Form\ResetPasswordFormType;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\ResetPasswordRequestFormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // if ($this->getUser()) {
        //     return $this->redirectToRoute('target_path');
        // }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    // Permettre en donnant l'email d'avoir un lien de reset token
    #[Route(path: '/forget_pass', name: 'app_forgotten_password')]
    public function forgottenPassword(Request $request, AdminRepository $usersRepository,
    TokenGeneratorInterface $tokenGenerator, EntityManagerInterface $manager,
    SendMailService $mail): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            //on va chercher l'utilisateur par son email
            $user =  $usersRepository->findOneByEmail($form->get('email')->getData());
            
            // On vérifie si on a bien un utilisateur avec l'email
            if($user) {
                //: On génère un token de réinitialisation (on utilise l'inteface Symfony mais on aurait pû utiliser notre JWTService)
                $token = $tokenGenerator->generateToken();
                $user->setResetToken($token);
                $manager->persist($user);
                $manager->flush();

                //: On génère un lien de réinitialisation du mot de passe (après avoir créer la route)
                $url = $this->generateUrl('app_reset_password', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                // On crée les données du mail
                $context = compact('url', 'user');
                //: envoi du mail 
                $mail->send(
                    'no-reply@guestbook.fr',
                    $user->getEmail(),
                    'Réinitialisation du mot de passe sur le site du livre d\'or Symfony',
                    'password_reset',
                    $context
                );

                $this->addFlash('forget_message_success', 'Email envoyé avec succès');
                return $this->redirectToRoute('app_login');

            }
            // Si l'utilisateur est null => son email n'existe pas en BDD
            $this->addFlash('forget_message_error','un problème est survenu ! 😟');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password_request.html.twig', [
            'requestPassForm' => $form->createView(),
        ]);
        
    }



     // Permettre d'avoir une route pour générer 1 lien de reset du token
     #[Route(path:'/reset_password/{token}', name: 'app_reset_password')]
     public function resetPass(string $token, Request $request, AdminRepository $usersRepository,
                                EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        
        // On vérifie si on a ce token dans la BDD
        $user = $usersRepository->findOneByResetToken($token);

        if ($user) {
            $form = $this->createForm(ResetPasswordFormType::class);

            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // On efface le token
                $user->setResetToken('');
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
                );
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('reset_passord_success', 'Mot de passe changé avec succès ! 👍');
                return $this->redirectToRoute('app_login');
            }

            return $this->render('security/reset_password.html.twig', [
                'passForm' => $form->createView()
            ]);
        }

        $this->addFlash('reset_passord_error', 'Jeton d\'authentification invalide');
        return $this->redirectToRoute('app_login');
    }
}
