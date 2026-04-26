<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source;

enum SourceType: string
{
    case PlainJson = 'plainJson';
    case SuperProductivity = 'superProductivity';
}
