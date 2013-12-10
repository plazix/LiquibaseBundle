<?php

namespace RtxLabs\LiquibaseBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TagCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('liquibase:tag')
            ->setDescription('\'Tags\' the current database state for future rollback')
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
        if (empty($this->connectionName)) {
            foreach ($this->dbConnections as $connectionName) {
                $output->writeln('Process ' . $tagName . ' for ' . $connectionName . ' connection.');

                $this->runner->runTag($tagName, $connectionName);
            }
        } else {
            $output->writeln('Set tag ' . $tagName . ' for ' . $this->connectionName . ' connection.');

            $this->runner->runTag($tagName, $this->connectionName);
        }
    }
}
