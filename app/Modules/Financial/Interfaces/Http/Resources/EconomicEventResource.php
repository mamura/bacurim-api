<?php

namespace App\Modules\Financial\Interfaces\Http\Resources;

use App\Modules\Financial\Domain\Entities\EconomicRecord;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class EconomicEventResource extends JsonResource
{
    public function __construct(private readonly EconomicRecord $record)
    {
        parent::__construct($record);
    }

    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $r = $this->record;
        $e = $r->event;
        $reference = new FinancialDate(now('UTC')->format('Y-m-d'));

        return ['public_id' => $e->identity->publicId, 'description' => $e->description, 'nature' => $e->nature->value, 'amount' => $e->amount->amount, 'currency' => $e->amount->currency, 'competence_date' => $e->competenceDate->value, 'category' => ['public_id' => $e->category->identity->publicId, 'name' => $e->category->name, 'nature' => $e->category->nature->value, 'status' => $e->category->status->value], 'commitments' => array_map(fn ($c) => FinancialCommitmentResource::fields($c, $reference), $r->commitments), 'origin' => 'manual', 'created_by' => $e->createdBy, 'editable' => $r->lockedAt === null, 'version' => $e->identity->version, 'created_at' => $e->identity->createdAt->format('Y-m-d\TH:i:s.u\Z'), 'updated_at' => $e->identity->updatedAt->format('Y-m-d\TH:i:s.u\Z')];
    }
}
