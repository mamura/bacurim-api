<?php

namespace App\Modules\Financial\Interfaces\Http\Resources;

use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FinancialCommitmentResource extends JsonResource
{
    public function __construct(private readonly FinancialCommitment $commitment)
    {
        parent::__construct($commitment);
    }

    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        return self::fields($this->commitment, new FinancialDate(now('UTC')->format('Y-m-d')));
    }

    /** @return array<string,mixed> */
    public static function fields(FinancialCommitment $c, FinancialDate $reference): array
    {
        return ['public_id' => $c->identity->publicId, 'event_public_id' => $c->event->identity->publicId, 'nature' => $c->nature->value, 'expected_amount' => $c->expectedAmount->amount, 'pending_amount' => $c->pendingAmount()->amount, 'currency' => $c->expectedAmount->currency, 'due_date' => $c->dueDate->value, 'status' => 'pending', 'temporal_status' => $c->temporalStatus($reference), 'reference_date' => $reference->value, 'version' => $c->identity->version, 'created_at' => $c->identity->createdAt->format('Y-m-d\TH:i:s.u\Z'), 'updated_at' => $c->identity->updatedAt->format('Y-m-d\TH:i:s.u\Z')];
    }
}
