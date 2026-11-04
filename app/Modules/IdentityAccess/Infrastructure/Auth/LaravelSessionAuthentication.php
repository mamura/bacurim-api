<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Application\Contracts\SessionAuthentication;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UseCases\GetUser;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Auth\SessionGuard;
use SensitiveParameter;

final readonly class LaravelSessionAuthentication implements SessionAuthentication
{
    public function __construct(private SessionGuard $guard, private GetUser $users) {}

    public function login(EmailAddress $email, #[SensitiveParameter] string $password): UserData
    {
        if (! $this->guard->attempt(['email' => $email->value, 'password' => $password], false)) {
            throw new ApplicationFailure(FailureKind::Unauthenticated, 'identity.invalid_credentials', 'The supplied credentials are invalid.');
        }
        $user = $this->guard->user();
        if (! $user instanceof UserRecord || $user->refresh()->status !== UserStatus::Active) {
            $this->logout();
            throw new ApplicationFailure(FailureKind::Unauthenticated, 'identity.invalid_credentials', 'The supplied credentials are invalid.');
        }
        $session = $this->guard->getSession();
        $session->regenerate();
        $session->put('auth.identity_version', $user->version);
        $session->put('auth.authenticated_at', now()->getTimestamp());

        return $this->users->execute($user->public_id);
    }

    public function logout(): void
    {
        $this->guard->logout();
        $this->guard->getSession()->invalidate();
        $this->guard->getSession()->regenerateToken();
    }

    public function current(): ?UserData
    {
        $user = $this->guard->user();

        return $user instanceof UserRecord ? $this->users->execute($user->public_id) : null;
    }
}
