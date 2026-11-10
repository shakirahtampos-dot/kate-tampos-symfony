<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'full_name', length: 100)]
    private string $fullName = '';

    #[ORM\Column(length: 150, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 255)]
    private string $password = '';

    #[ORM\Column(length: 20)]
    private string $role = 'customer';

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $fullName): static { $this->fullName = $fullName; return $this; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): static { $this->isActive = $isActive; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        return match ($this->role) {
            'admin' => ['ROLE_ADMIN', 'ROLE_STAFF', 'ROLE_USER'],
            'staff' => ['ROLE_STAFF', 'ROLE_USER'],
            default => ['ROLE_USER'],
        };
    }
}