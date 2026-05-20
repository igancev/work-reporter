<?php

declare(strict_types=1);

namespace Igancev\WorkReporter;

use Symfony\Component\Console\Output\OutputInterface;

final class Banner
{
    private const array WORK_LINES = [
        '█   █ █▀█ █▀▄ █▄▀',
        '█ █ █ █ █ █▄▀ ██ ',
        '▀▀ ▀▀ ▀▀▀ ▀ ▀ ▀ ▀',
    ];

    private const array SEPARATOR_LINES = [
        '    ',
        ' ▄▄ ',
        '    ',
    ];

    private const array REPORTER_LINES = [
        '█▀▄ █▀▀ █▀▄ █▀█ █▀▄ ▀█▀ █▀▀ █▀▄',
        '█▄▀ █▀  █▄▀ █ █ █▄▀  █  █▀  █▄▀',
        '▀ ▀ ▀▀▀ ▀   ▀▀▀ ▀ ▀  ▀  ▀▀▀ ▀ ▀',
    ];

    private const array WORK_START_RGB = [31, 85, 195];
    private const array WORK_END_RGB = [104, 159, 241];
    private const array REPORTER_START_RGB = [251, 67, 251];
    private const array REPORTER_END_RGB = [251, 64, 109];

    public static function render(OutputInterface $output): void
    {
        $output->writeln('');

        for ($row = 0; $row < count(self::WORK_LINES); $row++) {
            $line = self::colorize(
                self::WORK_LINES[$row],
                self::WORK_START_RGB,
                self::WORK_END_RGB,
            );
            $line .= "\033[0m" . self::SEPARATOR_LINES[$row];
            $line .= self::colorize(
                self::REPORTER_LINES[$row],
                self::REPORTER_START_RGB,
                self::REPORTER_END_RGB,
            );

            $output->writeln($line . "\033[0m");
        }
    }

    /**
     * @return string[]
     */
    public static function plain(): array
    {
        $lines = [];
        for ($row = 0; $row < count(self::WORK_LINES); $row++) {
            $lines[] = self::WORK_LINES[$row]
                . self::SEPARATOR_LINES[$row]
                . self::REPORTER_LINES[$row];
        }

        return $lines;
    }

    /**
     * @param int[] $startRgb
     * @param int[] $endRgb
     */
    private static function colorize(string $text, array $startRgb, array $endRgb): string
    {
        $chars = mb_str_split($text);
        $maxCol = mb_strlen($text) - 1;
        $result = '';

        foreach ($chars as $col => $char) {
            if ($char === ' ') {
                $result .= $char;
                continue;
            }

            $t = $maxCol > 0 ? $col / $maxCol : 0.0;
            $r = (int) round($startRgb[0] + ($endRgb[0] - $startRgb[0]) * $t);
            $g = (int) round($startRgb[1] + ($endRgb[1] - $startRgb[1]) * $t);
            $b = (int) round($startRgb[2] + ($endRgb[2] - $startRgb[2]) * $t);

            $result .= "\033[38;2;{$r};{$g};{$b}m{$char}";
        }

        return $result;
    }
}
