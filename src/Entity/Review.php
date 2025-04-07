<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Review",
 *     description="Review entity"
 * )
 */
#[ORM\Entity]
class Review
{
    /**
     * @OA\Property(type="integer", example=1)
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['review:list', 'review:detail', 'book:detail'])]
    private $id;

    /**
     * @OA\Property(ref="#/components/schemas/User")
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:list', 'review:detail', 'book:detail'])]
    private $reviewer;

    /**
     * @OA\Property(ref="#/components/schemas/Book")
     */
    #[ORM\ManyToOne(targetEntity: Book::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['review:list', 'review:detail'])]
    private $book;

    /**
     * @OA\Property(type="string", example="This book was absolutely fascinating. The character development was superb and the plot kept me engaged throughout.")
     */
    #[ORM\Column(type: 'text')]
    #[Groups(['review:list', 'review:detail', 'book:detail'])]
    #[Assert\NotBlank(message: 'Review text cannot be empty')]
    #[Assert\Length(min: 10, minMessage: 'Review text must be at least {{ limit }} characters long')]
    private $reviewText;

    /**
     * @OA\Property(type="string", format="date-time", example="2023-04-15T14:30:00+00:00")
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['review:list', 'review:detail', 'book:detail'])]
    private $createdAt;

    /**
     * @OA\Property(type="string", format="date-time", nullable=true, example="2023-04-16T09:45:00+00:00")
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['review:list', 'review:detail', 'book:detail'])]
    private $updatedAt;

    /**
     * @OA\Property(type="integer", minimum=1, maximum=5, example=4)
     */
    #[ORM\Column(type: 'integer')]
    #[Groups(['review:list', 'review:detail', 'book:detail'])]
    #[Assert\NotBlank(message: 'Rating is required')]
    #[Assert\Range(notInRangeMessage: 'Rating must be between {{ min }} and {{ max }}', min: 1, max: 5)]
    private $rating = 0;

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        $this->rating = $rating;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @OA\Property(type="boolean", example=true)
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->reviewer === $user;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReviewer(): ?User
    {
        return $this->reviewer;
    }

    public function setReviewer(User $reviewer): self
    {
        $this->reviewer = $reviewer;

        return $this;
    }

    public function getBook(): ?Book
    {
        return $this->book;
    }

    public function setBook(Book $book): self
    {
        $this->book = $book;

        return $this;
    }

    public function getReviewText(): ?string
    {
        return $this->reviewText;
    }

    public function setReviewText(string $reviewText): self
    {
        $this->reviewText = $reviewText;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
