<?php

namespace App\Modules\Financial\Application\UseCases;

use App\Modules\Financial\Application\Contracts\FinancialClock;
use App\Modules\Financial\Application\Contracts\FinancialIds;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCategoryRepository;
use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Entities\FinancialCategory;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class InitializeFinancialCategories
{
    public function __construct(private FinancialCategoryRepository $categories, private FinancialOperation $operation, private FinancialClock $clock, private FinancialIds $ids) {}

    /** @return list<FinancialCategory> */
    public function execute(TenantContext $context): array
    {
        return $this->operation->run($context, true, function () use ($context) {
            $result = [];
            foreach ([['income', 'Receitas'], ['cost', 'Custos'], ['expense', 'Despesas']] as [$nature,$name]) {
                $key = 'core-v1:'.$nature;
                $category = $this->categories->findDefault($context, $key);
                if ($category === null) {
                    $now = $this->clock->now();
                    $category = $this->categories->save($context, new FinancialCategory(new FinancialIdentity(null, $this->ids->generate(), $context->organizationId, 0, $now, $now), $name, EconomicNature::from($nature), defaultKey: $key));
                }
                $result[] = $category;
            }

            return $result;
        });
    }
}
