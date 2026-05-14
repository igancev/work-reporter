<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

/**
 * @extends WorkReporterException<array{input: string}>
 */
final class InvalidDurationException extends WorkReporterException
{
    public static function forInput(string $input, ?string $reason = null): self
    {
        $message = sprintf('Invalid duration format: "%s"', $input);
        if ($reason !== null) {
            $message .= sprintf('. %s', $reason);
        }

        return new self($message, ['input' => $input]);
    }
}
