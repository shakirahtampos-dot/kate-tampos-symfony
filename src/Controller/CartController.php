<?php

namespace App\Controller;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CartController extends AbstractController
{
    #[Route('/cart', name: 'app_cart')]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);

        $items = [];
        $total = 0.0;

        if ($cart) {
            $items = $em->getRepository(CartItem::class)->findBy(['cart' => $cart], ['id' => 'DESC']);

            foreach ($items as $item) {
                $total += (float) $item->getProduct()->getPrice() * $item->getQuantity();
            }
        }

        return $this->render('cart/index.html.twig', [
            'items' => $items,
            'total' => $total,
        ]);
    }

    #[Route('/cart/add', name: 'app_cart_add', methods: ['POST'])]
    public function add(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $productId = $request->request->get('product_id');

        if (!is_numeric($productId)) {
            return $this->redirectToRoute('app_home');
        }

        $product = $em->getRepository(Product::class)->find((int) $productId);

        if (!$product) {
            return $this->redirectToRoute('app_home');
        }

        if ($product->getStock() <= 0) {
            $this->addFlash('error', 'Sorry, that item is out of stock.');
            return $this->redirectToRoute('app_home');
        }

        $cart = $em->getRepository(Cart::class)->findOneBy(['user' => $user]);

        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $em->persist($cart);
            $em->flush();
        }

        $cartItem = $em->getRepository(CartItem::class)->findOneBy([
            'cart' => $cart,
            'product' => $product,
        ]);

        if ($cartItem) {
            $newQuantity = $cartItem->getQuantity() + 1;

            if ($newQuantity > $product->getStock()) {
                $this->addFlash('error', 'You have reached the stock limit for this item.');
                return $this->redirectToRoute('app_home');
            }

            $cartItem->setQuantity($newQuantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setCart($cart);
            $cartItem->setProduct($product);
            $cartItem->setQuantity(1);
            $em->persist($cartItem);
        }

        $em->flush();

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/update', name: 'app_cart_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $em): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $cartItemId = $request->request->get('cart_item_id');
        $action = $request->request->get('action');

        if (!is_numeric($cartItemId) || !in_array($action, ['increase', 'decrease'], true)) {
            return $this->redirectToRoute('app_cart');
        }

        $item = $em->getRepository(CartItem::class)->find((int) $cartItemId);

        if (!$item || $item->getCart()->getUser()->getId() !== $user->getId()) {
            return $this->redirectToRoute('app_cart');
        }

        $stock = $item->getProduct()->getStock();

        if ($action === 'increase') {
            $newQuantity = min($item->getQuantity() + 1, $stock);
        } else {
            $newQuantity = max($item->getQuantity() - 1, 1);
        }

        $item->setQuantity($newQuantity);
        $em->flush();

        return $this->redirectToRoute('app_cart');
    }

    #[Route('/cart/remove/{id}', name: 'app_cart_remove', requirements: ['id' => '\d+'])]
    public function remove(int $id, EntityManagerInterface $em): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $item = $em->getRepository(CartItem::class)->find($id);

        if ($item && $item->getCart()->getUser()->getId() === $user->getId()) {
            $em->remove($item);
            $em->flush();
        }

        return $this->redirectToRoute('app_cart');
    }
}