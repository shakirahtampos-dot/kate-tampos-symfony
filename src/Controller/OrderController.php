<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class OrderController extends AbstractController
{
    public function __construct(private Connection $db)
    {
    }

    /** @return array{0: int|null, 1: array, 2: float} */
    private function loadCart(int $userId): array
    {
        $cartId = $this->db->fetchOne('SELECT id FROM cart WHERE user_id = ?', [$userId]);
        if (!$cartId) {
            return [null, [], 0.0];
        }

        $sql = 'SELECT ci.product_id, ci.quantity, p.name, p.price, p.image, p.stock FROM cart_items ci INNER JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = ?';
        $items = $this->db->fetchAllAssociative($sql, [$cartId]);

        $total = 0.0;
        foreach ($items as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
            $total += $item['subtotal'];
        }
        unset($item);

        return [(int) $cartId, $items, $total];
    }

    private function hasStockProblem(array $items): bool
    {
        foreach ($items as $item) {
            if ($item['quantity'] > $item['stock']) {
                return true;
            }
        }

        return false;
    }

    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function checkout(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        [, $items, $total] = $this->loadCart($user->getId());

        if (empty($items)) {
            return $this->redirectToRoute('app_cart');
        }

        if ($this->hasStockProblem($items)) {
            $this->addFlash('error', 'Some items are no longer available in that quantity.');

            return $this->redirectToRoute('app_cart');
        }

        return $this->render('order/checkout.html.twig', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/checkout/place', name: 'app_order_place', methods: ['POST'])]
    public function place(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('place_order', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('app_checkout');
        }

        $address = trim((string) $request->request->get('delivery_address', ''));
        if ($address === '') {
            $this->addFlash('error', 'Please enter your address.');

            return $this->redirectToRoute('app_checkout');
        }

        [$cartId, $items, $total] = $this->loadCart($user->getId());

        if (empty($items)) {
            return $this->redirectToRoute('app_cart');
        }

        if ($this->hasStockProblem($items)) {
            $this->addFlash('error', 'Some items are no longer available in that quantity.');

            return $this->redirectToRoute('app_cart');
        }

        $this->db->beginTransaction();

        try {
            $orderSql = 'INSERT INTO orders (user_id, delivery_address, total_amount, status, notification_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())';
            $this->db->executeStatement($orderSql, [$user->getId(), $address, $total, 'Pending']);
            $orderId = (int) $this->db->lastInsertId();

            $itemSql = 'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)';
            $stockSql = 'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?';

            foreach ($items as $item) {
                $this->db->executeStatement($itemSql, [$orderId, $item['product_id'], $item['quantity'], $item['price']]);

                $updated = $this->db->executeStatement($stockSql, [$item['quantity'], $item['product_id'], $item['quantity']]);

                if ($updated === 0) {
                    throw new \RuntimeException('Not enough stock for ' . $item['name']);
                }
            }

            $this->db->executeStatement('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->addFlash('error', 'Order failed: ' . $e->getMessage());

            return $this->redirectToRoute('app_checkout');
        }

        return $this->redirectToRoute('app_order_success', ['id' => $orderId]);
    }

    #[Route('/order/success/{id}', name: 'app_order_success', requirements: ['id' => '\d+'])]
    public function success(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $sql = 'SELECT id, total_amount, status, delivery_address, created_at FROM orders WHERE id = ? AND user_id = ?';
        $order = $this->db->fetchAssociative($sql, [$id, $user->getId()]);

        if (!$order) {
            return $this->redirectToRoute('app_orders');
        }

        return $this->render('order/success.html.twig', ['order' => $order]);
    }

    #[Route('/orders', name: 'app_orders')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $sql = 'SELECT id, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC';
        $orders = $this->db->fetchAllAssociative($sql, [$user->getId()]);

        return $this->render('order/index.html.twig', ['orders' => $orders]);
    }

    #[Route('/orders/{id}', name: 'app_order_show', requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $orderSql = 'SELECT id, delivery_address, total_amount, status, created_at FROM orders WHERE id = ? AND user_id = ?';
        $order = $this->db->fetchAssociative($orderSql, [$id, $user->getId()]);

        if (!$order) {
            return $this->redirectToRoute('app_orders');
        }

        $itemsSql = 'SELECT oi.product_id, oi.quantity, oi.price, p.name, p.image FROM order_items oi INNER JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ? ORDER BY oi.id ASC';
        $items = $this->db->fetchAllAssociative($itemsSql, [$id]);

        foreach ($items as &$item) {
            $item['subtotal'] = $item['price'] * $item['quantity'];
        }
        unset($item);

        return $this->render('order/show.html.twig', ['order' => $order, 'items' => $items]);
    }
}