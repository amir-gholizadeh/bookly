<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="User",
 *     description="User entity"
 * )
 */
#[ORM\Entity]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * @OA\Property(type="integer", example=1)
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:basic', 'review:list', 'review:detail', 'book:detail'])]
    private $id;

    /**
     * @OA\Property(type="string", example="user@example.com")
     */
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:basic', 'review:list', 'review:detail', 'book:detail'])]
    private $email;

    /**
     * @OA\Property(type="string", example="password123")
     */
    #[ORM\Column(type: 'string')]
    private $password;

    /**
     * @OA\Property(type="string", example="John Doe")
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['user:basic', 'review:list', 'review:detail', 'book:detail'])]
    private $name;

    /**
     * @OA\Property(type="string", nullable=true, example="profile1.jpg")
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:basic', 'review:detail'])]
    private $profilePicturePath;

    /**
     * @OA\Property(type="array", @OA\Items(type="string"), example={"ROLE_USER"})
     */
    #[ORM\Column(type: 'json')]
    private $roles = [];

    /**
     * @OA\Property(type="string", example="newPassword123")
     */
    private $plainPassword;

    // Implement UserInterface methods
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getProfilePicturePath(): ?string
    {
        return $this->profilePicturePath;
    }

    public function setProfilePicturePath(?string $profilePicturePath): self
    {
        $this->profilePicturePath = $profilePicturePath;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getSalt(): ?string
    {
        // Not needed when using the "bcrypt" algorithm in security.yaml
        return null;
    }

    public function getUsername(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        $this->plainPassword = null;
    }

    /**
     * @OA\Property(type="string", example="/uploads/profile_pictures/profile1.jpg")
     */
    #[Groups(['user:basic', 'review:detail'])]
    public function getProfilePictureUrl(): ?string
    {
        return $this->profilePicturePath ? '/uploads/profile_pictures/'.$this->profilePicturePath : null;
    }
}
