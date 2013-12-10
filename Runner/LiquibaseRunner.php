<?php

namespace RtxLabs\LiquibaseBundle\Runner;

use Doctrine\Bundle\DoctrineBundle\Registry;

use Symfony\Component\FileSystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

class LiquibaseRunner
{
    /** @var \Symfony\Component\FileSystem\Filesystem */
    private $filesystem;

    /** @var array */
    private $dbConnections;

    /** @var string */
    private $projectRootDir;

    public function __construct(KernelInterface $kernel, Filesystem $filesystem, Registry $doctrine)
    {
        $this->filesystem = $filesystem;
        $this->projectRootDir = realpath($kernel->getRootDir() . '/../');

        foreach ($doctrine->getConnections() as $connectionName => $connection) {
            /** @var $connection \Doctrine\DBAL\Connection */
            $this->dbConnections[$connectionName] = $connection->getParams();
        }
    }

    //--- liquibase command

    public function runUpdate($changeLogFile, $connectionName = 'default')
    {
        $command = $this->getBaseCommand($this->dbConnections[$connectionName]);
        $command .= ' --changeLogFile=' . $changeLogFile;
        $command .= ' update';

        print $command;

        $this->run($command);
    }

    public function runRollback($changeLogFile, $tagName, $connectionName = 'default')
    {
        $command = $this->getBaseCommand($this->dbConnections[$connectionName]);
        $command .= ' --changeLogFile=' . $changeLogFile;
        $command .= ' rollback ' . $tagName;

        print $command;

        $this->run($command);
    }

    public function runDiff($connectionName = 'default')
    {
        //
    }

    public function runTag($tagName, $connectionName = 'default')
    {
        $command = $this->getBaseCommand($this->dbConnections[$connectionName]);
        $command .= ' tag ' . $tagName;

        print $command;

        $this->run($command);
    }

    public function runStatus()
    {
        //
    }

    //--- helper methods

    protected function run($command)
    {
        $process = proc_open(
            $command,
            array(array('pipe', 'r'), array('pipe', 'w'), array('pipe', 'w')),
            $pipes,
            $this->projectRootDir
        );
        if (is_resource($process)) {
            $log = stream_get_contents($pipes[1]);

            $errorLog = stream_get_contents($pipes[2]);
            if (!empty($errorLog)) {
                print $errorLog;
            }

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            return 0 == proc_close($process);
        }

        return false;
    }

    protected function getBaseCommand($connectionParams)
    {
        $command = 'java -jar ' . __DIR__ . '/../Resources/vendor/liquibase.jar '
            . ' --driver=' . $this->getJdbcDriverName($connectionParams['driver'])
            . ' --url=' . $this->getJdbcDsn($connectionParams);

        if ($connectionParams['user'] != "") {
            $command .= ' --username=' . $connectionParams['user'];
        }

        if ($connectionParams['password'] != "") {
            $command .= ' --password=' . $connectionParams['password'];
        }

        $command .= ' --classpath=' . $this->getJdbcDriverClassPath($connectionParams['driver']);

        return $command;
    }

    protected function getJdbcDriverName($dbalDriver)
    {
        switch($dbalDriver) {
            case 'pdo_mysql':
            case 'mysql':
                $driver = 'com.mysql.jdbc.Driver';
                break;
            default:
                throw new \RuntimeException('No JDBC-Driver found for ' . $dbalDriver);
        }

        return $driver;
    }

    protected function getJdbcDriverClassPath($dbalDriver)
    {
        $dir = dirname(__FILE__) . "/../Resources/vendor/jdbc/";

        switch($dbalDriver) {
            case 'pdo_mysql':
            case 'mysql':
                $dir .= 'mysql-connector-java-5.1.18-bin.jar';
                break;
            default:
                throw new \RuntimeException('No JDBC-Driver found for ' . $dbalDriver);
        }

        return $dir;
    }

    protected function getJdbcDsn($dbalParams)
    {
        switch ($dbalParams['driver']) {
            case 'pdo_mysql':
            case 'mysql':
                return $this->getMysqlJdbcDsn($dbalParams);
                break;
            default:
                throw new \RuntimeException('Database not supported');
        }
    }

    protected function getMysqlJdbcDsn($dbalParams)
    {
        $dsn = 'jdbc:mysql://';
        if ($dbalParams['host'] != "") {
            $dsn .= $dbalParams['host'];
        }
        else {
            $dsn .= 'localhost';
        }

        if ($dbalParams['port'] != "") {
            $dsn .= ":" . $dbalParams['port'];
        }

        $dsn .= '/' . $dbalParams['dbname'];
        if ('utf8' == strtolower($dbalParams['charset'])) {
            $dsn .= '?useUnicode=true&characterEncoding=utf-8';
        }

        return '"' . $dsn . '"';
    }
}
