<?php

namespace Hyungseok\PHPSwitcher\Console;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class SwitchCommand extends Command
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('switch')
            ->setDescription('Switch PHP version with valet.')
            ->addArgument('version', InputArgument::REQUIRED, 'Select the version you want to replace.');
    }

    /**
     * Executes the current command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (! preg_match('/^\d(\.\d)$/', $input->getArgument('version'))) {
            throw new InvalidArgumentException('The type of version entered is invalid.');
        }

        $output->writeln('<info>Checking dependencies...</info>');

        $composer = $this->findComposer();

        $valet = $this->findValet($composer);

        $brew = $this->findBrew();

        $output->writeln('<info>Importing installed PHP versions...</info>');

        $installedVersions = $this->getInstalledVersions();

        $output->writeln('<info>The PHP versions you have: { ' . implode(' ', $installedVersions) . ' }</info>');

        $this->validateVersion($input, $installedVersions);

        $commands = [
            "{$valet} stop",
            "{$brew} unlink " . implode(' ', $installedVersions),
            "{$brew} link --force --overwrite php@{$input->getArgument('version')}",
            "${brew} services start php@{$input->getArgument('version')}",
            "{$composer} global config platform.php " . $this->getFullVersion($input),
            "{$composer} global update",
            'rm -f ~/.config/valet/valet.sock',
            "{$valet} install",
        ];

        $process = new Process(implode('&&', $commands), null, null, null, null);

        if (getenv('environment') !== 'testing' && '\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) use ($output) {
            $output->write($line);
        });

        $output->writeln('<comment>PHP ' . $input->getArgument('version') . ' and Valet ready! Build something amazing.</comment>');
    }

    /**
     * Get the full version of the entered version.
     *
     * @param InputInterface $input
     * @example 7.1.29
     * @return string
     */
    protected function getFullVersion($input)
    {
        $fullVersion = [];

        (new Process('brew info php@' . $input->getArgument('version')))
            ->run(function ($type, $line) use (&$fullVersion) {
                $pattern = '/\d(\.\d)(\.\d+)/';

                if (preg_match($pattern, $line)) {
                    preg_match($pattern, $line, $fullVersion);
                }
            });

        return $fullVersion[0];
    }

    /**
     * Determine that the version you entered is installed on your Homebrew.
     *
     * @param InputInterface $input
     * @param array $versions
     * @return bool
     */
    protected function validateVersion($input, $versions)
    {
        foreach ($versions as $version) {
            if (strpos($version, $input->getArgument('version')) !== false) {
                return true;
            }
        }

        throw new InvalidArgumentException(
            "PHP {$input->getArgument('version')} is not installed on Homebrew.\n
            Please try again after installation:\n\n
            $ brew install php@{$input->getArgument('version')}"
        );
    }

    /**
     * Get all installed PHP versions of the current user's Homebrew.
     *
     * @example ['php@5.6', 'php@7.1', 'php@7.2']
     * @return array
     */
    protected function getInstalledVersions()
    {
        $versions = [];

        (new Process('brew list | grep php'))->run(function ($type, $line) use (&$versions) {
            $versions = array_filter(explode("\n", $line), function ($version) {
                return strpos($version, 'php@') !== false;
            });
        });

        return $versions;
    }

    /**
     * Confirm that Homebrew is installed and get the command.
     *
     * @return string
     */
    protected function findBrew()
    {
        (new Process('brew -v'))->run(function($type) {
            if (Process::ERR === $type) {
                throw new RuntimeException(
                    "Homebrew is not installed.\n
                    Please try again after installation:\n\n
                    $ /usr/bin/ruby -e \"$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/master/install)\""
                );
            }
        });

        return 'brew';
    }

    /**
     * Verify that Valet is installed and get the command.
     *
     * @param string $composer
     * @return string
     */
    protected function findValet($composer)
    {
        $valet = '';
        $commands = [
            'global' => $composer . ' global show -iN',
            'local' => $composer . ' show -iN',
        ];

        foreach ($commands as $location => $command) {
            (new Process($command))
                ->run(function ($type, $line) use ($location, &$valet) {
                    if (preg_match('/valet/', $line)) {
                        $valet = $location === 'global'
                            ? 'valet'
                            : getcwd() . '/vendor/bin/valet';
                    }
                });
        }

        if (! $valet) {
            throw new RuntimeException(
                "Valet is not installed.\n
                Please try again after installation:\n\n
                $ composer global require laravel/valet"
            );
        }

        return $valet;
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd() . '/composer.phar')) {
            return '"' . PHP_BINARY . '" composer.phar';
        }

        return 'composer';
    }
}