<?php

declare(strict_types=1);

namespace Galeas\Api\BoundedContext\Identity\User\Event;

use Galeas\Api\BoundedContext\Identity\User\Aggregate\User;
use Galeas\Api\BoundedContext\Identity\User\ValueObject\AccountDetails;
use Galeas\Api\BoundedContext\Identity\User\ValueObject\Email;
use Galeas\Api\BoundedContext\Identity\User\ValueObject\HashedPassword;
use Galeas\Api\BoundedContext\Identity\User\ValueObject\UnverifiedEmail;
use Galeas\Api\BoundedContext\Identity\User\ValueObject\VerificationCode;
use Galeas\Api\Common\Event\EventTrait;
use Galeas\Api\Common\Id\Id;
use Galeas\Api\Primitive\PrimitiveCreation\Email\EmailVerificationCodeCreator;
use Galeas\Api\Primitive\PrimitiveTransformation\Hash\BCryptPasswordHash;

class SignedUp implements EventCreatedUser
{
    use EventTrait;

    private string $primaryEmail;
    
    private string $primaryEmailVerificationCode;
    
    private string $hashedPassword;

    private string $username;

    private bool $termsOfUseAccepted;

    public function primaryEmail(): string
    {
        return $this->primaryEmail;
    }

    public function primaryEmailVerificationCode(): string
    {
        return $this->primaryEmailVerificationCode;
    }

    public function hashedPassword(): string
    {
        return $this->hashedPassword;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function termsOfUseAccepted(): bool
    {
        return $this->termsOfUseAccepted;
    }

    /**
     * @throws CouldNotHashWithBCrypt
     */
    public static function new(
        Id $eventId,
        Id $aggregateId,
        int $aggregateVersion,
        Id $causationId,
        Id $correlationId,
        \DateTimeImmutable $recordedOn,
        array $metadata,
        string $primaryEmail,
        string $password,
        string $username,
        bool $termsOfUseAccepted
    ): SignedUp {
        $event = new self(
            $eventId,
            $aggregateId,
            $aggregateVersion,
            $causationId,
            $correlationId,
            $recordedOn,
            $metadata
        );

        $event->primaryEmail = $primaryEmail;
        $event->primaryEmailVerificationCode = EmailVerificationCodeCreator::create();
        $hash = BCryptPasswordHash::hash($password, 10);
        if (null === $hash) {
            throw new CouldNotHashWithBCrypt();
        }
        $event->hashedPassword = $hash;
        $event->username = $username;
        $event->termsOfUseAccepted = $termsOfUseAccepted;

        return $event;
    }

    public function createUser(): User
    {
        return User::fromProperties(
            $this->aggregateId(),
            $this->aggregateVersion(),
            UnverifiedEmail::fromEmailAndVerificationCode(
                Email::fromEmail(
                    $this->primaryEmail
                ),
                VerificationCode::fromVerificationCode(
                    $this->primaryEmailVerificationCode
                )
            ),
            HashedPassword::fromHash(
                $this->hashedPassword
            ),
            AccountDetails::fromDetails(
                $this->username,
                $this->termsOfUseAccepted
            )
        );
    }
}
