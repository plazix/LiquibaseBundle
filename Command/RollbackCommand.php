<?php

namespace RtxLabs\LiquibaseBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class RollbackCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('liquibase:rollback:run')
            ->setDescription('Rolls back the database to the state it was in when the tag was applied')
            ->addArgument('tag', InputArgument::REQUIRED, 'Tag');

        parent::configure();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $tagName = $input->getArgument('tag');
        $changeSetLogs = $this->findChangeSetLogs($this->bundleName, $this->connectionName);
        if (empty($changeSetLogs)) {
            $output->writeln('Not found changeset files');
        } else {
            foreach ($changeSetLogs as $connectionName => $changeSetFilename) {
                $output->writeln('Process ' . $changeSetFilename . ' for ' . $connectionName . ' connection.');

                $this->runner->runRollback($changeSetFilename, $tagName, $connectionName);
            }
        }
    }
}
