#!/usr/bin/php
<?php

require __DIR__.'/../../../vendor/autoload.php';

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Application;

class PreCommit extends Application
{
    private $output;
    private $input;
    private $error = false;

    public function __construct()
    {
        parent::__construct('', '');
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $files = $this->extractCommitedFiles();
        $files = $this->filterNonPhpFiles($files);

        $this->checkPhpSyntax($files);
        $this->checkCodingStandards($files);

        return (int) $this->error;
    }

    private function extractCommitedFiles()
    {
        $process = new Process("git diff --cached --name-only --diff-filter=ACMR");
        $process->run();

        return explode("\n", $process->getOutput());
    }

    private function filterNonPhpFiles($files)
    {
        return array_filter(
            $files,
            function ($file) {
                return preg_match('/\.php$/', $file);
            }
        );
    }

    private function checkPhpSyntax($files)
    {
        foreach ($files as $file) {
            $process = new Process(
                "git show :$file | php -n -l -ddisplay_errors=1 -derror_reporting=E_ALL -dlog_errors=0"
            );
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(
                    sprintf('<error>Php syntax error at %s</error>', $file)
                );
                $this->error = true;
            }
        }
    }

    private function checkCodingStandards($files)
    {
        foreach ($files as $file) {
            $process = new Process(
                "git show :$file | php-cs-fixer fix --diff --fixers=-psr0 -"
            );
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln(
                    sprintf('<error>Php Coding Standard error at %s</error>', $file)
                );
                $this->error = true;
            }
        }
    }
}

$console = new PreCommit();
$console->run();
