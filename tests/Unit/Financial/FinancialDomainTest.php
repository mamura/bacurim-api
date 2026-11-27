<?php

namespace Tests\Unit\Financial;

use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\CommitmentNature;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Entities\EconomicEvent;
use App\Modules\Financial\Domain\Entities\EconomicRecord;
use App\Modules\Financial\Domain\Entities\FinancialAccount;
use App\Modules\Financial\Domain\Entities\FinancialCategory;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\Money;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class FinancialDomainTest extends TestCase
{
    private function identity(int $suffix = 1, int $org = 1): FinancialIdentity
    {
        $now = new DateTimeImmutable('2026-11-27T12:00:00Z');

        return new FinancialIdentity(null, sprintf('00000000-0000-4000-8000-%012d', $suffix), $org, 0, $now, $now);
    }

    public static function invalidMoney(): array
    {
        return [[1.1], [1], [true], [null], ['-1'], ['1.001'], ['1,20'], ['1e2'], ['NaN'], [''], [' 1'], ['01'], ['10000000000000000'], ['1.20\n']];
    }

    #[DataProvider('invalidMoney')]
    public function test_money_rejects_ambiguous_or_imprecise_values(mixed $amount): void
    {
        $this->expectException(InvalidFinancialData::class);
        new Money($amount);
    }

    public function test_money_is_exact_and_normalized_without_float(): void
    {
        $this->assertSame('0.30', (new Money('0.1'))->plus(new Money('0.20'))->amount);
        $this->assertSame('9999999999999999.99', (new Money('9999999999999999.98'))->plus(new Money('0.01'))->amount);
        $this->assertSame('1.00', (new Money('2'))->minus(new Money('1'))->amount);
        $this->expectException(InvalidFinancialData::class);
        (new Money('9999999999999999.99'))->plus(new Money('0.01'));
    }

    public function test_only_approved_currency_is_accepted(): void
    {
        $this->expectException(InvalidFinancialData::class);
        new Money('1', 'USD');
    }

    public static function invalidDates(): array
    {
        return [['2026-02-29'], ['2026-11-31'], ['27/11/2026'], ['2026-1-01'], ['2026-01-01T00:00:00Z']];
    }

    #[DataProvider('invalidDates')]
    public function test_civil_dates_are_strict(string $date): void
    {
        $this->expectException(InvalidFinancialData::class);
        new FinancialDate($date);
    }

    public function test_account_revisions_preserve_identity_and_never_contain_balance(): void
    {
        $account = new FinancialAccount($this->identity(), ' Banco ', AccountType::Bank);
        $updated = $account->revise('Novo nome', AccountType::Digital, FinancialStatus::Inactive, $account->identity->updatedAt->modify('+1 second'));
        $this->assertSame($account->identity->publicId, $updated->identity->publicId);
        $this->assertSame($account->identity->organizationId, $updated->identity->organizationId);
        $this->assertSame(FinancialStatus::Inactive, $updated->status);
        $this->assertFalse(property_exists($updated, 'balance'));
        $this->expectException(InvalidFinancialData::class);
        $account->revise(' ', AccountType::Cash, FinancialStatus::Active, $account->identity->updatedAt);
    }

    public static function natures(): array
    {
        return [[EconomicNature::Income, CommitmentNature::Receivable], [EconomicNature::Cost, CommitmentNature::Payable], [EconomicNature::Expense, CommitmentNature::Payable]];
    }

    #[DataProvider('natures')]
    public function test_economic_value_is_recognized_once_and_due_date_is_independent(EconomicNature $nature, CommitmentNature $expected): void
    {
        $category = new FinancialCategory($this->identity(1), 'Categoria', $nature);
        $event = new EconomicEvent($this->identity(2), 'Serviço', $nature, new Money('0.30'), new FinancialDate('2026-01-15'), $category, $this->identity(4)->publicId);
        $a = new FinancialCommitment($this->identity(3), $event, new Money('0.1'), new FinancialDate('2026-02-01'));
        $b = new FinancialCommitment($this->identity(4), $event, new Money('0.20'), new FinancialDate('2026-03-01'));
        $record = new EconomicRecord($event, [$a, $b]);
        $this->assertSame($expected, $a->nature);
        $this->assertSame('0.30', $record->event->amount->amount);
        $this->assertSame('2026-01-15', $event->competenceDate->value);
        $this->assertSame('0.10', $a->pendingAmount()->amount);
        $this->assertSame('overdue', $a->temporalStatus(new FinancialDate('2026-02-02')));
        $this->assertSame('upcoming', $a->temporalStatus(new FinancialDate('2026-02-01')));
        $this->expectException(InvalidFinancialData::class);
        new EconomicRecord($event, [$a]);
    }

    public function test_inactive_category_restores_history_but_is_not_eligible_for_new_event(): void
    {
        $category = new FinancialCategory($this->identity(), 'History', EconomicNature::Income, FinancialStatus::Inactive);
        $event = new EconomicEvent($this->identity(2), 'Historical event', EconomicNature::Income, new Money('1'), new FinancialDate('2020-01-01'), $category, $this->identity(3)->publicId);
        $this->assertSame('Historical event', $event->description);
        $this->expectException(InvalidFinancialData::class);
        $event->requireEligibleCategory();
    }

    public function test_cross_tenant_category_is_rejected(): void
    {
        $category = new FinancialCategory($this->identity(1, 2), 'Other', EconomicNature::Income);
        $this->expectException(InvalidFinancialData::class);
        new EconomicEvent($this->identity(2), 'Denied', EconomicNature::Income, new Money('1'), new FinancialDate('2026-01-01'), $category, $this->identity(3)->publicId);
    }

    public function test_mismatched_nature_is_rejected(): void
    {
        $category = new FinancialCategory($this->identity(), 'Expense', EconomicNature::Expense);
        $this->expectException(InvalidFinancialData::class);
        new EconomicEvent($this->identity(2), 'Denied', EconomicNature::Income, new Money('1'), new FinancialDate('2026-01-01'), $category, $this->identity(3)->publicId);
    }

    public function test_category_cannot_parent_itself(): void
    {
        $this->expectException(InvalidFinancialData::class);
        new FinancialCategory($this->identity(), 'Cycle', EconomicNature::Income, parentPublicId: $this->identity()->publicId);
    }
}
