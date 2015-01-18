<?php

namespace Epilgrim\PhpHooks;

use Composer\Script\Event;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

class CreateSymlinks
{
    private $event;

    public static function create(Event $event)
    {
        $creator = new self($event);

        return $creator->run();
    }

    private function __construct(Event $event)
    {
        $this->event = $event;
    }

    public function run()
    {
        if (!$this->event->isDevMode()) {
            return true;
        }

        $extras = $this->event->getComposer()->getPackage()->getExtra();

        if (!isset($extras['epilgrim-php-hooks'])) {
            throw new \InvalidArgumentException('There is no configuration for the hooks.');
        }

        $searchAt = $extras['epilgrim-php-hooks'];
        $gitDirectories = $this->getHookDestinations($searchAt);
        foreach ($gitDirectories as $directory) {
            $fullPath = $directory->getRealPath();
            if ($this->hasHook($fullPath)) {
                continue;
            }
            $this->linkHook($fullPath);
        }

        return true;
    }

    private function getHookDestinations($searchAt)
    {
        $finder = new Finder();
        foreach ($searchAt as $location) {
            $finder->in(__DIR__.'/../../..'.$location);
        }

        return $finder
            ->ignoreVCS(false)
            ->ignoreDotFiles(false)
            ->exclude('vendor')
            ->path('/\.git\/hooks$/')
        ;
    }

    private function hasHook($directory)
    {
        $finder = new Finder();
        $finder
            ->in($directory)
            ->depth('== 0')
            ->files()
            ->name('pre-commit')
        ;

        return count($finder) !== 0;
    }

    private function linkHook($directory)
    {
        $hook = realpath(__DIR__.'/../../../vendor/epilgrim/php-hooks/PreCommit.php');
        $link = sprintf('%s/%s', $directory, 'pre-commit');
        $this->event->getIO()
            ->write(sprintf(
                '<info>Creating Symlink from %s to %s</info>',
                $link,
                $hook
            )
        );
        $process = new Process(
            sprintf("ln -s %s %s", $hook, $link)
        );
        $process->run();
    }
}
