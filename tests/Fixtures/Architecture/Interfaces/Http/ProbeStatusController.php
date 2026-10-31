<?php

namespace Tests\Fixtures\Architecture\Interfaces\Http;

use Illuminate\Http\JsonResponse;
use Tests\Fixtures\Architecture\Application\ReadProbeStatus;

final readonly class ProbeStatusController
{
    public function __construct(private ReadProbeStatus $readStatus) {}

    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['status' => $this->readStatus->handle()->value]);
    }
}
