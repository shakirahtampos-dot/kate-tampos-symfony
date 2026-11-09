<?php

namespace App\Twig;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_count', $this->cartCount(...)),
        ];
    }

    public function cartCount(): int
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return 0;
        }

        return (int) $this->em->createQuery(
            'SELECT COALESCE(SUM(ci.quantity), 0)
             FROM App\Entity\CartItem ci
             JOIN ci.cart c
             WHERE c.user = :user'
        )
            ->setParameter('user', $user)
            ->getSingleScalarResult();
    }
}