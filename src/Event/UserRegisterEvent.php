<?php

namespace App\Event;

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

class UserRegisterEvent extends Event
{
    const NAME = 'user.register';

    /**
     * Permet d'obtenier l'utilisateur qui s'enregistre
     *
     * @var User
     */
    private $registeredUser;

    public function __construct($registeredUser)
    {
        $this->registeredUser = $registeredUser;
    }

    /**
     * Retourne l'utilisateur
     *
     * @return User
     */
    public function getRegisteredUser(): User
    {
        return $this->registeredUser;
    }
}
