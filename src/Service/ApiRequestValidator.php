<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiRequestValidator
{
    private $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    public function validateReviewInput(Request $request): array
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $constraints = new Assert\Collection([
            'bookId' => [
                new Assert\NotBlank(['message' => 'Book ID is required']),
                new Assert\Type(['type' => 'numeric', 'message' => 'Book ID must be a number']),
            ],
            'rating' => [
                new Assert\NotBlank(['message' => 'Rating is required']),
                new Assert\Range([
                    'min' => 1,
                    'max' => 5,
                    'notInRangeMessage' => 'Rating must be between {{ min }} and {{ max }}',
                ]),
            ],
            'reviewText' => [
                new Assert\NotBlank(['message' => 'Review text is required']),
                new Assert\Length([
                    'min' => 10,
                    'minMessage' => 'Review text must be at least {{ limit }} characters long',
                ]),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);

        $errors = [];
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                $errors[$propertyPath] = $violation->getMessage();
            }
        }

        return $errors;
    }

    public function validateBookInput(Request $request): array
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $constraints = new Assert\Collection([
            'title' => [
                new Assert\NotBlank(['message' => 'Title is required']),
                new Assert\Length(['max' => 255, 'maxMessage' => 'Title cannot be longer than {{ limit }} characters']),
            ],
            'author' => [
                new Assert\NotBlank(['message' => 'Author is required']),
                new Assert\Length(['max' => 255, 'maxMessage' => 'Author cannot be longer than {{ limit }} characters']),
            ],
            'numberOfPages' => [
                new Assert\NotBlank(['message' => 'Number of pages is required']),
                new Assert\Type(['type' => 'numeric', 'message' => 'Number of pages must be a number']),
                new Assert\Positive(['message' => 'Number of pages must be positive']),
            ],
            'summary' => [
                new Assert\NotBlank(['message' => 'Summary is required']),
            ],
            'genre' => [
                new Assert\NotBlank(['message' => 'Genre is required']),
                new Assert\Length(['max' => 255, 'maxMessage' => 'Genre cannot be longer than {{ limit }} characters']),
            ],
        ]);

        $violations = $this->validator->validate($data, $constraints);

        $errors = [];
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                $errors[$propertyPath] = $violation->getMessage();
            }
        }

        return $errors;
    }
}
