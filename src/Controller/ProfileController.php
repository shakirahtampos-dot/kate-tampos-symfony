<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    public function __construct(private Connection $db)
    {
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, UserPasswordHasherInterface $hasher): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $user->getId();
        $sql = 'SELECT id, full_name, email FROM users WHERE id = ?';
        $row = $this->db->fetchAssociative($sql, [$userId]);
        $fullName = $row['full_name'] ?? '';
        $email = $row['email'] ?? '';
        $error = null;

        if ($request->isMethod('POST')) {
            $fullName = trim((string) $request->request->get('full_name', ''));
            $email = trim((string) $request->request->get('email', ''));
            $newPassword = (string) $request->request->get('new_password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if ($fullName === '' || $email === '') {
                $error = 'Full name and email are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($newPassword !== '' && $newPassword !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                $existing = $this->db->fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$email, $userId]);

                if ($existing) {
                    $error = 'That email address is already being used.';
                } else {
                    if ($newPassword !== '') {
                        $hashed = $hasher->hashPassword($user, $newPassword);
                        $updateSql = 'UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?';
                        $this->db->executeStatement($updateSql, [$fullName, $email, $hashed, $userId]);
                    } else {
                        $updateSql = 'UPDATE users SET full_name = ?, email = ? WHERE id = ?';
                        $this->db->executeStatement($updateSql, [$fullName, $email, $userId]);
                    }

                    $this->addFlash('profile_success', 'Profile updated successfully.');

                    return $this->redirectToRoute('app_profile');
                }
            }
        }

        return $this->render('profile/index.html.twig', [
            'full_name' => $fullName,
            'email' => $email,
            'error' => $error,
        ]);
    }
}