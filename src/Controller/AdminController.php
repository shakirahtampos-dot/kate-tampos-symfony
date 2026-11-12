<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    private const STATUSES = ['Pending', 'Approved', 'Ready for Pickup', 'Picked Up', 'Completed', 'Cancelled'];

    public function __construct(private Connection $db)
    {
    }

    #[Route('/admin', name: 'app_admin')]
    public function dashboard(Request $request): Response
    {
        $byStatus = $this->db->fetchAllKeyValue('SELECT status, COUNT(*) FROM orders GROUP BY status');
        $counts = [
            'total' => array_sum($byStatus),
            'pending' => (int) ($byStatus['Pending'] ?? 0),
            'approved' => (int) ($byStatus['Approved'] ?? 0),
            'completed' => (int) ($byStatus['Completed'] ?? 0),
            'cancelled' => (int) ($byStatus['Cancelled'] ?? 0),
            'users' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM users'),
            'staff' => (int) $this->db->fetchOne("SELECT COUNT(*) FROM users WHERE role = 'staff'"),
            'products' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM products'),
        ];

        $q = trim((string) $request->query->get('q', ''));
        $status = (string) $request->query->get('status', '');

        $sql = 'SELECT o.id, o.total_amount, o.status, o.created_at, u.full_name FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE 1 = 1';
        $params = [];

        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR CAST(o.id AS CHAR) = ?)';
            array_push($params, $like, $like, ltrim($q, '#'));
        }

        if (in_array($status, self::STATUSES, true)) {
            $sql .= ' AND o.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY o.created_at DESC';

        return $this->render('admin/dashboard.html.twig', [
            'counts' => $counts,
            'orders' => $this->db->fetchAllAssociative($sql, $params),
            'q' => $q,
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    #[Route('/admin/orders/{id}', name: 'app_admin_order', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function order(int $id, Request $request): Response
    {
        $orderSql = 'SELECT o.*, u.email, u.full_name FROM orders o INNER JOIN users u ON o.user_id = u.id WHERE o.id = ?';
        $order = $this->db->fetchAssociative($orderSql, [$id]);

        if (!$order) {
            return $this->redirectToRoute('app_admin');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('order_status', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Invalid security token. Please try again.');

                return $this->redirectToRoute('app_admin_order', ['id' => $id]);
            }

            $newStatus = (string) $request->request->get('status', '');

            if (in_array($newStatus, self::STATUSES, true)) {
                if ($newStatus !== $order['status']) {
                    $this->db->executeStatement('UPDATE orders SET status = ?, notification_read = 0 WHERE id = ?', [$newStatus, $id]);
                    $this->addFlash('admin_success', 'Order status updated successfully.');
                }

                return $this->redirectToRoute('app_admin_order', ['id' => $id]);
            }
        }

        $itemsSql = 'SELECT oi.quantity, oi.price, p.name, p.image FROM order_items oi INNER JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? ORDER BY oi.id ASC';
        $items = $this->db->fetchAllAssociative($itemsSql, [$id]);

        return $this->render('admin/order.html.twig', [
            'order' => $order,
            'items' => $items,
            'statuses' => self::STATUSES,
        ]);
    }
}