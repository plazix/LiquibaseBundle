<?php

namespace RtxLabs\LiquibaseBundle\Command;

use Doctrine\Bundle\DoctrineBundle\Registry;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use RtxLabs\LiquibaseBundle\Runner\LiquibaseRunner;

abstract class AbstractCommand extends ContainerAwareCommand
{
    /** @var array */
    protected $dbConnections;

    /** @var LiquibaseRunner */
    protected $runner;

    /** @var string */
    protected $bundleName;

    /** @var string */
    protected $connectionName;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->addArgument('connection', InputArgument::OPTIONAL, 'Doctrine connection name')
            ->addArgument(
                'bundle',
                InputArgument::OPTIONAL,
                'The name of the bundle (shortcut notation AcmeDemoBundle) for that the changlogs should run or all '
                . 'bundles if no one is given'
            );
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->runner = $this->getContainer()->get('liquibase');

        /** @var Registry $doctrine */
        $doctrine = $this->getContainer()->get('doctrine');
        $this->dbConnections = array_keys($doctrine->getConnections());

        $excludeConnections = $this->getContainer()->getParameter('liquibase.exclude.connections');
        if (is_array($excludeConnections)) {
            $this->dbConnections = array_diff($this->dbConnections, $excludeConnections);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bundleName = $input->getArgument('bundle');
        $this->connectionName = $input->getArgument('connection');

        return 0;
    }

    /**
     * @param string $bundleName Bundle name
     * @param string $connectionName Doctrine connection name
     * @return array array(connectionName => changeSetFilename,..)
     */
    protected function findChangeSetLogs($bundleName = '', $connectionName = '')
    {
        /** @var \Symfony\Component\HttpKernel\KernelInterface $kernel */
        $kernel = $this->getContainer()->get('kernel');
        $projectRootDir = realpath($kernel->getRootDir() . '/../');

        if (empty($bundleName)) {
            $changeSetLogFilename = 'app/Resources/liquibase/changelog-{connection_name}-{env}.xml' ;
        } else {

            /** @var \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle */
            $bundle = $this->getContainer()->get('kernel')->getBundle($bundleName);

            $changeSetLogFilename = $changelogFile = str_replace(
                $projectRootDir,
                '',
                $bundle->getPath() . '/Resources/liquibase/changelog-{connection_name}-{env}.xml'
            );
        }

        $changeSetLogs = array();
        if (empty($connectionName)) {
            foreach ($this->dbConnections as $connectionName) {
                $filename = str_replace(
                    array('{connection_name}', '{env}'),
                    array($connectionName, $kernel->getEnvironment()),
                    $changeSetLogFilename
                );
                if (is_file($projectRootDir . '/' . $filename)) {
                    $changeSetLogs[$connectionName] = $filename;
                }
            }
        } else {
            $filename = str_replace('{connection_name}', $connectionName, $changeSetLogFilename);
            if (is_file($projectRootDir . '/' . $filename)) {
                $changeSetLogs[$connectionName] = $filename;
            }
        }

        return $changeSetLogs;
    }
}
