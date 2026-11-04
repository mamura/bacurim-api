<?php

namespace Tests\Unit\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\SessionAuthentication;
use App\Modules\IdentityAccess\Application\UseCases\GetAuthenticatedUser;
use App\Modules\IdentityAccess\Application\UseCases\LoginUser;
use App\Modules\IdentityAccess\Application\UseCases\LogoutUser;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use PHPUnit\Framework\TestCase;

class AuthenticationUseCasesTest extends TestCase
{
    public function test_invalid_email_never_reaches_session_adapter(): void
    {
        $adapter = $this->createMock(SessionAuthentication::class);
        $adapter->expects($this->never())->method('login');
        $this->expectException(ApplicationFailure::class);
        (new LoginUser($adapter))->execute('invalid', 'testing-only-password');
    }

    public function test_login_normalizes_email_and_preserves_password_spaces(): void
    {
        $adapter = $this->createMock(SessionAuthentication::class);
        $adapter->expects($this->once())->method('login')->with($this->callback(fn (EmailAddress $email): bool => $email->value === 'ana+tag@example.com'), ' password with spaces ')->willThrowException(new ApplicationFailure(FailureKind::Unauthenticated, 'identity.invalid_credentials', 'Invalid credentials.'));
        $this->expectException(ApplicationFailure::class);
        (new LoginUser($adapter))->execute(' ANA+tag@example.com ', ' password with spaces ');
    }

    public function test_current_user_requires_authentication(): void
    {
        $adapter = $this->createMock(SessionAuthentication::class);
        $adapter->method('current')->willReturn(null);
        $this->expectException(ApplicationFailure::class);
        (new GetAuthenticatedUser($adapter))->execute();
    }

    public function test_logout_delegates_session_invalidation(): void
    {
        $adapter = $this->createMock(SessionAuthentication::class);
        $adapter->expects($this->once())->method('logout');
        (new LogoutUser($adapter))->execute();
    }
}
