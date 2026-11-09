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

        $items = $this->db->fetchAllAssociative(
            'SELECT ci.product_id, ci.quantity, p.name, p.price, p.image, p.stock
             FROM cart_items ci
             INNER JOIN products p ON ci.product_id = p.id
             WHERE ci.cart_id = ?',
            [$cartId]
        );

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

        $address = trim((string) $request->request->get('delivery_address', ''));
        if ($address === '') {
            $this->addFlash('error', 'Please enter your delivery address.');

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
            $this->db->executeStatement(
                'INSERT INTO orders (user_id, delivery_address, total_amount, status, notification_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())',
                [$user->getId(), $address, $total, 'Pending']
            );
            $orderId = (int) $this->db->lastInsertId();

            foreach ($items as $item) {
                $this->db->executeStatement(
                    'INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)',
                    [$orderId, $item['product_id'], $item['quantity'], $item['price']]
                );

                $updated = $this->db->executeStatement(
                    'UPDATE products