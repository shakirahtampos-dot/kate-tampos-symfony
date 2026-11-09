<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $error = '';
        $fullName = '';
        $email = '';

        if ($request->isMethod('POST')) {
            $fullName = trim((string) $request->request->get('full_name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $confirm = (string) $request->request->get('confirm_password', '');

            $error = $this->validateRegistration($fullName, $email, $password, $confirm);

            if ($error === '' && $em->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = 'An account with this email already exists.';
            }

            if ($error === '') {
                $user = new User();
                $user->setFullName($fullName);
                $user->setEmail($email);
                $user->setPassword($hasher->hashPassword($user, $password));

                $em->persist($user);
                $em->flush();

                return $this->redirectToRoute('app_login', ['registered' => 1]);
            }
        }

        return $this->render('registration/register.html.twig', [
            'error' => $error,
            'full_name' => $fullName,
            'email' => $email,
        ]);
    }

    private function validateRegistration(string $fullName, string $email, string $password, string $confirm): string
    {
        if ($fullName === '' || $email === '' || $password === '' || $confirm === '') {
            return 'Please fill in all fields.';
        }

        if (!preg_match("/^[a-zA-Z\s.'-]+$/", $fullName)) {
            return 'Full name can only contain letters, spaces, periods, hyphens, and apostrophes.';
        }

        if (strlen($fullName) < 2) {
            return 'Full name must be at least 2 characters.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address.';
        }

        if (strlen($password) < 8) {
            return 'Password must be at least 8 characters.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/[a-z]/', $password)) {
            return 'Password must contain at least one lowercase letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            return 'Password must contain at least one number.';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            return 'Password must contain at least one special character.';
        }

        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }

        return '';
    }
}