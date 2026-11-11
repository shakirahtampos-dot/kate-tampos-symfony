<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    private const ROLES = ['admin', 'staff', 'customer'];

    public function __construct(private Connection $db, private UserPasswordHasherInterface $hasher)
    {
    }

    /** @return array{0: array, 1: string|null} */
    private function readForm(Request $request, ?int $id): array
    {
        $data = [
            'full_name' => trim((string) $request->request->get('full_name', '')),
            'email' => trim((string) $request->request->get('email', '')),
            'role' => (string) $request->request->get('role', ''),
        ];
        $password = (string) $request->request->get('password', '');

        $error = null;
        if ($data['full_name'] === '' || $data['email'] === '') {
            $error = 'Full name and email are required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($data['role'], self::ROLES, true)) {
            $error = 'Please choose a valid role.';
        } elseif ($id === null && $password === '') {
            $error = 'Password is required for a new account.';
        } elseif ($password !== '' && strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($this->db->fetchOne('SELECT id FROM users WHERE email = ? AND id != ?', [$data['email'], $id ?? 0])) {
            $error = 'That email address is already being used.';
        }

        return [$data, $error];
    }

    private function checkToken(Request $request, string $tokenId): bool
    {
        return $this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'));
    }

    private function back(string $type, string $message): Response
    {
        $this->addFlash($type, $message);

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/admin/users', name: 'app_admin_users')]
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $role = (string) $request->query->get('role', '');

        $sql = 'SELECT id, full_name, email, role, is_active, created_at FROM users WHERE 1 = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (full_name LIKE ? OR email LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        if (in_array($role, self::ROLES, true)) {
            $sql .= ' AND role = ?';
            $params[] = $role;
        }
        $sql .= ' ORDER BY id DESC';

        return $this->render('admin/users.html.twig', [
            'users' => $this->db->fetchAllAssociative($sql, $params),
            'q' => $q,
            'role' => $role,
            'roles' => self::ROLES,
        ]);
    }

    #[Route('/admin/users/new', name: 'app_admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = ['full_name' => '', 'email' => '', 'role' => 'staff'];
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->checkToken($request, 'user_form')) {
                $error = 'Invalid security token. Please try again.';
            } else {
                [$user, $error] = $this->readForm($request, null);

                if (!$error) {
                    $hash = $this->hasher->hashPassword(new User(), (string) $request->request->get('password', ''));
                    $sql = 'INSERT INTO users (full_name, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())';
                    $this->db->executeStatement($sql, [$user['full_name'], $user['email'], $hash, $user['role']]);

                    return $this->back('admin_success', 'Account created successfully.');
                }
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'heading' => 'Add User',
            'subtitle' => 'Create a new admin, staff, or customer account.',
            'submit' => 'Create Account',
            'is_new' => true,
            'roles' => self::ROLES,
            'user' => $user,
            'error' => $error,
        ]);
    }

    #[Route('/admin/users/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $user = $this->db->fetchAssociative('SELECT id, full_name, email, role FROM users WHERE id = ?', [$id]);
        if (!$user) {
            return $this->back('admin_error', 'User not found.');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->checkToken($request, 'user_form')) {
                $error = 'Invalid security token. Please try again.';
            } else {
                [$data, $error] = $this->readForm($request, $id);
                $user = array_merge($user, $data);

                if (!$error && $id === $this->getUser()->getId() && $data['role'] !== 'admin') {
                    $error = 'You cannot change your own role.';
                }

                if (!$error) {
                    $password = (string) $request->request->get('password', '');
                    if ($password !== '') {
                        $hash = $this->hasher->hashPassword(new User(), $password);
                        $sql = 'UPDATE users SET full_name = ?, email = ?, role = ?, password = ? WHERE id = ?';
                        $this->db->executeStatement($sql, [$data['full_name'], $data['email'], $data['role'], $hash, $id]);
                    } else {
                        $sql = 'UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?';
                        $this->db->executeStatement($sql, [$data['full_name'], $data['email'], $data['role'], $id]);
                    }

                    return $this->back('admin_success', 'Account updated successfully.');
                }
            }
        }

        return $this->render('admin/user_form.html.twig', [
            'heading' => 'Edit User',
            'subtitle' => 'Update the account details or reset the password.',
            'submit' => 'Save Changes',
            'is_new' => false,
            'roles' => self::ROLES,
            'user' => $user,
            'error' => $error,
        ]);
    }

    #[Route('/admin/users/{id}/toggle', name: 'app_admin_user_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(int $id, Request $request): Response
    {
        if (!$this->checkToken($request, 'user_action')) {
            return $this->back('admin_error', 'Invalid security token. Please try again.');
        }
        if ($id === $this->getUser()->getId()) {
            return $this->back('admin_error', 'You cannot disable your own account.');
        }

        $this->db->executeStatement('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$id]);

        return $this->back('admin_success', 'Account status updated.');
    }

    #[Route('/admin/users/{id}/delete', name: 'app_admin_user_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->checkToken($request, 'user_action')) {
            return $this->back('admin_error', 'Invalid security token. Please try again.');
        }
        if ($id === $this->getUser()->getId()) {
            return $this->back('admin_error', 'You cannot delete your own account.');
        }

        $orders = (int) $this->db->fetchOne('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$id]);
        if ($orders > 0) {
            return $this->back('admin_error', 'This user already has orders and cannot be deleted. Disable the account instead.');
        }

        $this->db->executeStatement('DELETE FROM users WHERE id = ?', [$id]);

        return $this->back('admin_success', 'Account deleted.');
    }
}