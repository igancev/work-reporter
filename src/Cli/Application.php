<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Cli;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class Application extends BaseApplication
{
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        if (true === $input->hasParameterOption(['--version', '-V'], true)) {
            Banner::render($output, $this->getVersion());

            return 0;
        }

        return parent::doRun($input, $output);
    }
}
