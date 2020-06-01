<?php

namespace App\DataFixtures;

use App\Entity\Announce;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }
    public function load(ObjectManager $manager)
    {
        $faker = Factory::create('fr_FR');
        for ($u = 0; $u < 10; $u++) {
            $user = new User();
            $user->setFirstName($faker->firstName())
                ->setLastName($faker->lastName)
                ->setEmail($faker->email)
                ->setPassword($this->encoder->encodePassword($user, "password"))
                ->setEnabled(true);

            $manager->persist($user);

            for ($a = 0; $a < mt_rand(0, 6); $a++) {
                $announce = new Announce();
                $announce->setTitle($faker->sentence(4))
                    ->setContent('<p>' . $faker->paragraph . '</p>')
                    ->setCreatedAt($faker->dateTimeBetween('-3 months'))
                    ->setAuthor($user);
                $manager->persist($announce);
            }
        }

        $manager->flush();
    }
}
