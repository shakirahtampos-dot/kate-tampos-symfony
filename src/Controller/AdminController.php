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
    public function dashboard(): Response
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

        $sql = 'SELECT o.id, o.total_amount, o.status, o.created_at, u.full_name FROM orders o INNER JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC';
        $orders = $this->db->fetchAllAssociative($sql);

        return $this->render('admin/dashboard.html.twig', [
            'counts' => $counts,
            'orders' => $orders,
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

            $status = (string) $request->request->get('status', '');

            if (in_array($status, self::STATUSES, true)) {
                if ($status !== $order['status']) {
                    $this->db->executeStatement('UPDATE orders SET status = ?, notification_read = 0 WHERE id = ?', [$status, $id]);
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