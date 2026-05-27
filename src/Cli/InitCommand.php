<?php

declare(strict_types=1);

namespace Igancev\WorkReporter\Cli;

use Igancev\WorkReporter\Platform\HomeDirectory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class InitCommand extends Command
{
    private const string DEFAULT_CONFIG_DIR = '~/.config/work-reporter';
    private const string DEFAULT_CONFIG_PATH = '~/.config/work-reporter/config.yaml';

    private const string DEFAULT_CONFIG_CONTENT = <<<YAML
# Work Reporter configuration file
# Documentation: https://github.com/igancev/work-reporter

# Active Source type: superProductivity | plainJson
source: superProductivity

# Active Destination type: youTrack | somethingElse
destination: youTrack

# Configuration for each source type
sources:
  superProductivity:
    syncFilePath: ~/.config/superProductivity/__meta_
  plainJson:
    filePath: /path/to/time-entries.json

# Configuration for each destination type
destinations:
  youTrack:
    # Your YouTrack instance URL
    url: https://youtrack.example.com
    # Permanent token for authentication
    token: your-api-token
YAML;

    protected function configure(): void
    {
        $this
            ->setName('init')
            ->setDescription('Generate a default configuration file')
            ->setHelp(
                'Creates a default YAML configuration file at ' . self::DEFAULT_CONFIG_PATH . "\n"
                . 'You can then edit it to match your setup.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $home = HomeDirectory::resolve();
        $configDir = str_replace('~', $home, self::DEFAULT_CONFIG_DIR);
        $configPath = str_replace('~', $home, self::DEFAULT_CONFIG_PATH);

        if (file_exists($configPath)) {
            $io->warning(sprintf('Configuration file already exists at: %s', $configPath));
            if (!$io->confirm('Do you want to overwrite it?', false)) {
                $io->info('Aborted. Existing configuration was not modified.');
                return Command::SUCCESS;
            }
        }

        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0755, true)) {
                $io->error(sprintf('Failed to create directory: %s', $configDir));
                return Command::FAILURE;
            }
        }

        if (file_put_contents($configPath, self::DEFAULT_CONFIG_CONTENT) === false) {
            $io->error(sprintf('Failed to write configuration file: %s', $configPath));
            return Command::FAILURE;
        }

        $io->success(sprintf('Configuration file created at: %s', $configPath));
        $io->info('Edit the file to configure your source and destination settings.');

        return Command::SUCCESS;
    }
}
