<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route('/notification/read/{id}', name: 'app_notification_read', requirements: ['id' => '\d+'])]
    public function read(int $id, Connection $db): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $sql = 'UPDATE orders SET notification_read = 1 WHERE id = ? AND user_id = ?';
        $db->executeStatement($sql, [$id, $user->getId()]);

        return $this->redirectToRoute('app_order_show', ['id' => $id]);
    }
}