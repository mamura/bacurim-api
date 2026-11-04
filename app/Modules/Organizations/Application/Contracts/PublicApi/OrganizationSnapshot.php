<?php

namespace App\Modules\Organizations\Application\Contracts\PublicApi;

final readonly class OrganizationSnapshot
{
    public function __construct(public int $id, public string $publicId, public string $name, public bool $active) {}
}
