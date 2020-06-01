<?php

namespace App\Controller;

use App\Entity\User;
use App\Event\UserRegisterEvent;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use App\Security\TokenGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
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
}
