<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function findPaginatedBooks(int $page, int $limit, ?string $sortBy = null, ?string $search = null): Paginator
    {
        $queryBuilder = $this->createQueryBuilder('b');

        // Apply search if provided
        if ($search) {
            $queryBuilder
                ->where('b.title LIKE :search')
                ->orWhere('b.author LIKE :search')
                ->orWhere('b.genre LIKE :search')
                ->orWhere('b.summary LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // Apply sorting
        switch ($sortBy) {
            case 'title_asc':
                $queryBuilder->orderBy('b.title', 'ASC');
                break;
            case 'title_desc':
                $queryBuilder->orderBy('b.title', 'DESC');
                break;
            case 'author_asc':
                $queryBuilder->orderBy('b.author', 'ASC');
                break;
            case 'author_desc':
                $queryBuilder->orderBy('b.author', 'DESC');
                break;
            case 'rating_desc':
            case 'rating_asc':
                // For rating sorting, we need to join with reviews and calculate average
                $queryBuilder
                    ->leftJoin('b.reviews', 'r')
                    ->groupBy('b.id')
                    ->addSelect('AVG(r.rating) as HIDDEN avg_rating');

                if ('rating_desc' === $sortBy) {
                    $queryBuilder->orderBy('avg_rating', 'DESC');
                } else {
                    $queryBuilder->orderBy('avg_rating', 'ASC');
                }
                break;
            default:
                // Default sorting by most recent
                $queryBuilder->orderBy('b.id', 'DESC');
        }

        $query = $queryBuilder
            ->getQuery()
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return new Paginator($query);
    }
}
