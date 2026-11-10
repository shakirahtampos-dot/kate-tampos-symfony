<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminProductController extends AbstractController
{
    public function __construct(private Connection $db)
    {
    }

    /** @return array{0: array, 1: string|null} */
    private function readForm(Request $request): array
    {
        $data = [
            'name' => trim((string) $request->request->get('name', '')),
            'description' => trim((string) $request->request->get('description', '')),
            'price' => trim((string) $request->request->get('price', '')),
            'tag' => trim((string) $request->request->get('tag', '')),
            'image' => trim((string) $request->request->get('image', '')),
            'stock' => trim((string) $request->request->get('stock', '')),
        ];

        $error = null;
        if ($data['name'] === '' || $data['description'] === '' || $data['price'] === '' || $data['stock'] === '') {
            $error = 'Please fill in all required fields.';
        } elseif (!is_numeric($data['price']) || (float) $data['price'] < 0) {
            $error = 'Please enter a valid price.';
        } elseif (filter_var($data['stock'], FILTER_VALIDATE_INT) === false || (int) $data['stock'] < 0) {
            $error = 'Please enter a valid stock quantity.';
        }

        return [$data, $error];
    }

    #[Route('/admin/products', name: 'app_admin_products')]
    public function index(): Response
    {
        $products = $this->db->fetchAllAssociative('SELECT * FROM products ORDER BY id DESC');

        return $this->render('admin/products.html.twig', ['products' => $products]);
    }

    #[Route('/admin/products/new', name: 'app_admin_product_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $product = ['name' => '', 'description' => '', 'price' => '', 'tag' => '', 'image' => '', 'stock' => ''];
        $error = null;

        if ($request->isMethod('POST')) {
            [$product, $error] = $this->readForm($request);

            if (!$error) {
                $sql = 'INSERT INTO products (name, description, price, tag, image, stock, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())';
                $this->db->executeStatement($sql, [
                    $product['name'],
                    $product['description'],
                    $product['price'],
                    $product['tag'],
                    $product['image'],
                    (int) $product['stock'],
                ]);
                $this->addFlash('admin_success', 'Product added successfully.');

                return $this->redirectToRoute('app_admin_products');
            }
        }

        return $this->render('admin/product_form.html.twig', [
            'css' => 'add_product.css',
            'heading' => 'Add Product',
            'subtitle' => 'Add a new product to Kates Goodies.',
            'submit' => 'Add Product',
            'cancel' => 'Back',
            'product' => $product,
            'error' => $error,
        ]);
    }

    #[Route('/admin/products/{id}/edit', name: 'app_admin_product_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $product = $this->db->fetchAssociative('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$product) {
            $this->addFlash('admin_error', 'Product not found.');

            return $this->redirectToRoute('app_admin_products');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            [$product, $error] = $this->readForm($request);

            if (!$error) {
                $sql = 'UPDATE products SET name = ?, description = ?, price = ?, tag = ?, image = ?, stock = ? WHERE id = ?';
                $this->db->executeStatement($sql, [
                    $product['name'],
                    $product['description'],
                    $product['price'],
                    $product['tag'],
                    $product['image'],
                    (int) $product['stock'],
                    $id,
                ]);
                $this->addFlash('admin_success', 'Product updated successfully.');

                return $this->redirectToRoute('app_admin_products');
            }
        }

        return $this->render('admin/product_form.html.twig', [
            'css' => 'edit_product.css',
            'heading' => 'Edit Product',
            'subtitle' => 'Update the information for this product.',
            'submit' => 'Save Changes',
            'cancel' => 'Cancel',
            'product' => $product,
            'error' => $error,
        ]);
    }

    #[Route('/admin/products/{id}/delete', name: 'app_admin_product_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id): Response
    {
        $used = (int) $this->db->fetchOne('SELECT COUNT(*) FROM order_items WHERE product_id = ?', [$id]);

        if ($used > 0) {
            $this->addFlash('admin_error', 'This product is already in an order and cannot be deleted. Set its stock to 0 instead.');

            return $this->redirectToRoute('app_admin_products');
        }

        try {
            $this->db->executeStatement('DELETE FROM products WHERE id = ?', [$id]);
            $this->addFlash('admin_success', 'Product deleted.');
        } catch (\Throwable $e) {
            $this->addFlash('admin_error', 'Failed to delete the product.');
        }

        return $this->redirectToRoute('app_admin_products');
    }
}