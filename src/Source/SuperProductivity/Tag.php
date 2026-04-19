<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Source\SuperProductivity;

use InvalidArgumentException;

final readonly class Tag
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
        if (trim($this->id) === '') {
            throw new InvalidArgumentException('Tag id cannot be empty');
        }

        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Tag name cannot be empty');
        }
    }
}
