<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AfterLoginController extends AbstractController
{
    #[Route('/after-login', name: 'app_after_login')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin');
        }

        if ($this->isGranted('ROLE_STAFF')) {
            return $this->redirectToRoute('app_admin_products');
        }

        return $this->redirectToRoute('app_home');
    }
}