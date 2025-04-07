<?php

namespace App\Entity;

use App\Repository\BookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Annotations as OA;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * @OA\Schema(
 *     schema="Book",
 *     description="Book entity"
 * )
 */
#[ORM\Entity(repositoryClass: BookRepository::class)]
class Book
{
    /**
     * @OA\Property(type="integer", example=1)
     */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['book:list', 'book:detail', 'review:detail'])]
    private $id;

    /**
     * @OA\Property(type="string", example="The Great Gatsby")
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['book:list', 'book:detail', 'review:detail'])]
    private $title;

    /**
     * @OA\Property(type="string", example="F. Scott Fitzgerald")
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['book:list', 'book:detail', 'review:detail'])]
    private $author;

    /**
     * @OA\Property(type="integer", example=218)
     */
    #[ORM\Column(type: 'integer')]
    #[Groups(['book:detail'])]
    private $numberOfPages;

    /**
     * @OA\Property(type="string", example="Set in the Jazz Age on Long Island, the novel depicts narrator Nick Carraway's interactions with mysterious millionaire Jay Gatsby...")
     */
    #[ORM\Column(type: 'text')]
    #[Groups(['book:detail'])]
    private $summary;

    /**
     * @OA\Property(type="string", example="Fiction")
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['book:list', 'book:detail'])]
    private $genre;

    /**
     * @OA\Property(type="string", nullable=true, example="great_gatsby.jpg")
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['book:list', 'book:detail'])]
    private $coverImagePath;

    /**
     * @OA\Property(type="array", @OA\Items(ref="#/components/schemas/Review"))
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'book', orphanRemoval: true)]
    #[Groups(['book:detail'])]
    private Collection $reviews;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    /**
     * @OA\Property(type="number", format="float", example=4.5)
     */
    #[Groups(['book:list', 'book:detail'])]
    public function getAverageRating(): float
    {
        if ($this->reviews->isEmpty()) {
            return 0;
        }

        $sum = 0;
        foreach ($this->reviews as $review) {
            $sum += $review->getRating();
        }

        return $sum / $this->reviews->count();
    }

    /**
     * @OA\Property(type="integer", example=12)
     */
    #[Groups(['book:list', 'book:detail'])]
    public function getReviewCount(): int
    {
        return $this->reviews->count();
    }

    /**
     * @OA\Property(type="string", example="/uploads/cover_images/great_gatsby.jpg")
     */
    #[Groups(['book:detail'])]
    public function getCoverImageUrl(): ?string
    {
        return $this->coverImagePath ? '/uploads/cover_images/'.$this->coverImagePath : null;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setBook($this);
        }

        return $this;
    }

    public function removeReview(Review $review): self
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getBook() === $this) {
                $review->setBook(null);
            }
        }

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function getNumberOfPages(): ?int
    {
        return $this->numberOfPages;
    }

    public function setNumberOfPages(int $numberOfPages): self
    {
        $this->numberOfPages = $numberOfPages;

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): self
    {
        $this->genre = $genre;

        return $this;
    }

    public function getCoverImagePath(): ?string
    {
        return $this->coverImagePath;
    }

    public function setCoverImagePath(?string $coverImagePath): self
    {
        $this->coverImagePath = $coverImagePath;

        return $this;
    }
}
