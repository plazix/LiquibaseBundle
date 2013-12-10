<?php
namespace RtxLabs\LiquibaseBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('liquibase:update:run')
            ->setDescription('Updates database to current version');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $changeSetLogs = $this->findChangeSetLogs($this->bundleName, $this->connectionName);
        if (empty($changeSetLogs)) {
            $output->writeln('Not found changeset files');
        } else {
            foreach ($changeSetLogs as $connectionName => $changeSetFilename) {
                $output->writeln('Process ' . $changeSetFilename . ' for ' . $connectionName . ' connection.');

                $this->runner->runUpdate($changeSetFilename, $connectionName);
            }
        }
    }
}
