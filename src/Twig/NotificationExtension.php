<?php

namespace App\Twig;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class NotificationExtension extends AbstractExtension
{
    private ?array $cache = null;

    public function __construct(private Connection $db, private Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('notifications', [$this, 'getNotifications'])];
    }

    public function getNotifications(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $user = $this->security->getUser();
        if (!$user) {
            return $this->cache = [];
        }

        $sql = "SELECT id, status, created_at FROM orders WHERE user_id = ? AND status IN ('Approved', 'Ready for Pickup', 'Picked Up', 'Completed', 'Cancelled') AND notification_read = 0 ORDER BY created_at DESC LIMIT 10";
        $rows = $this->db->fetchAllAssociative($sql, [$user->getId()]);

        $messages = [
            'Approved' => 'Your order has been approved.',
            'Ready for Pickup' => 'Your order is ready for pickup.',
            'Picked Up' => 'Your order has been picked up.',
            'Completed' => 'Your order has been completed.',
            'Cancelled' => 'Your order has been cancelled.',
        ];

        foreach ($rows as &$row) {
            $row['message'] = $messages[$row['status']] ?? 'Your order status has been updated.';
        }
        unset($row);

        return $this->cache = $rows;
    }
}