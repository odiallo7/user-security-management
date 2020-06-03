<?php

namespace App\Controller;

use App\Entity\PasswordUpdate;
use App\Entity\User;
use App\Event\UserRegisterEvent;
use App\Form\PasswordResetType;
use App\Form\PasswordUpdateType;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PhpParser\Node\Stmt\TryCatch;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Twig\Environment;

class AccountController extends AbstractController
{

    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * @Route("/login", name="account_login")
     * @return Response
     */
    public function login(AuthenticationUtils $utils)
    {
        $error = $utils->getLastAuthenticationError();
        $username = $utils->getLastUsername();

        return $this->render('account/login.html.twig', [
            //'error' => $error !== null,
            'error' => $error,
            'username' => $username
        ]);
    }

    /**
     *@Route("/logout", name="account_logout")
     * @return void
     */
    public function logout()
    {
    }

    /**
     * @Route("/register", name="account_register")
     *
     * @return Response
     */
    public function register(
        Request $request,
        EntityManagerInterface $manager,
        UserPasswordEncoderInterface $encoder,
        EventDispatcherInterface $eventDispatcher,
        TokenGenerator $tokenGenerator
    ) {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $password = $encoder->encodePassword($user, $user->getPlainPassword());
            $user->setPassword($password);
            $user->setConfirmationToken($tokenGenerator->getRandomSecureToken(30));
            $manager->persist($user);
            $manager->flush();

            $userRegisterEvent = new UserRegisterEvent($user);
            $eventDispatcher->dispatch($userRegisterEvent, UserRegisterEvent::NAME);

            $this->addFlash('success', "Votre compte a été créé avec success");
            return $this->redirectToRoute('home_page');
        }
        return $this->render('account/register.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     *@Route("/confirm/{token}", name="account_confirm")
     * 
     */
    public function confirm(string $token, UserRepository $repo, EntityManagerInterface $manager)
    {
        $user = $repo->findOneBy([
            'confirmationToken' => $token
        ]);

        if (null !== $user) {
            $user->setEnabled(true);
            $user->setConfirmationToken('');

            $manager->flush();
        }
        return new Response($this->twig->render('account/confirmation.html.twig', [
            'user' => $user
        ]));
    }

    /**
     *@Route("/password-update", name="account_password_update")
     * @return Response
     */
    public function updatePassword(Request $request, EntityManagerInterface $manager, UserPasswordEncoderInterface $encoder)
    {
        $passwordUpdate = new PasswordUpdate();
        $user = $this->getUser();

        $form = $this->createForm(PasswordUpdateType::class, $passwordUpdate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!password_verify($passwordUpdate->getOldPassword(), $user->getPassword())) {
                $form->get('oldPassword')
                    ->addError(new FormError("Ce mot de passe n'est pas votre ancien mot de passe"));
            } else {
                $newPassword = $passwordUpdate->getNewPassword();
                $password = $encoder->encodePassword($user, $newPassword);

                $user->setPassword($password);

                $manager->flush();

                $this->addFlash(
                    'success',
                    "Votre mot de passe à bien été mis à jour !"
                );

                return $this->redirectToRoute('home_page');
            }
        }

        return $this->render('account/password.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     *@Route("/forgotten", name="account_forgotton")
     * @return Response
     */
    public function forgottenPass(
        Request $request,
        UserRepository $repo,
        TokenGeneratorInterface $tokenGenerator,
        EntityManagerInterface $manager,
        \Swift_Mailer $mailer
    ) {
        $form = $this->createForm(PasswordResetType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $user = $repo->findOneByEmail($data['email']);

            if (!$user) {
                $this->addFlash(
                    'danger',
                    "Cet email n'est pas valide, merci de renseigner un email ou de créer un compte"
                );
                return $this->redirectToRoute('account_login');
            }

            $token = $tokenGenerator->generateToken();

            try {
                $user->setPasswordToken($token);
                $manager->flush();
            } catch (\Exception $e) {
                $this->addFlash(
                    'warnig',
                    "Une erreur est survenue: " . '' . $e->getMessage()
                );
                return  $this->redirectToRoute('account_login');
            }

            $url = $this->generateUrl(
                'account_reset_password',
                ['token' => $token],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            $body = $this->render('email/forgotten.html.twig', [
                'user' => $user,
                'url' => $url
            ]);

            $message = (new \Swift_Message('Mot de passe oublié'))
                ->setFrom('contact@sym.com')
                ->setTo($user->getEmail())
                /*  ->setBody(
                    '<h2>Bonjour</h2>
                    <p>Pour initialiser votre mot de passe il faut cliquer sur le lien:  ' . $url . '</p>',
                    'text/html',
                    'utf-8'
                ) */
                ->setBody($body, 'text/html', 'utf-8');


            $mailer->send($message);
            $this->addFlash(
                'success',
                "Un email de vérification vous été envoyé merci de consulter votre compte
                pour pouvoir changer votre mot de passe"
            );
            return $this->redirectToRoute('home_page');
        }
        return $this->render('account/forgotten.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     *@Route("/reset-password/{token}", name="account_reset_password")
     * @return Response
     */
    public function reset(
        $token,
        Request $request,
        UserPasswordEncoderInterface $encoder,
        EntityManagerInterface $manager,
        UserRepository $repo
    ) {
        $user = $repo->findOneBy(['passwordToken' => $token]);

        if (!$user) {
            $this->addFlash(
                'warnig',
                "Une erreur est survenue! Reassayer avec un bon token"
            );
            return $this->redirectToRoute('account_login');
        }
        if ($request->isMethod('POST')) {
            $user->setPasswordToken(null);
            $password = $encoder->encodePassword($user, $request->request->get('password'));
            $user->setPassword($password);
            $manager->flush();
            $this->addFlash(
                'success',
                "Votre mot de passe a bien été réinitialisé !"
            );
            return $this->redirectToRoute('account_login');
        } else {
            return $this->render('account/password_init.html.twig', [
                'token' => $token
            ]);
        }
    }
}
