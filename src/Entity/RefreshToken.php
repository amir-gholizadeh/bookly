<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;
use OpenApi\Annotations as OA;


/**
 * @OA\Schema(
 *     schema="RefreshToken",
 *     description="Refresh token for JWT authentication"
 * )
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
