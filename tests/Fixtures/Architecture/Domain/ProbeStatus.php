<?php

namespace Tests\Fixtures\Architecture\Domain;

enum ProbeStatus: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
}
