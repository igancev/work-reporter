<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source;

enum SourceType: string
{
    case PlainJson = 'plain-json';
    case SuperProductivity = 'super-productivity';
}
