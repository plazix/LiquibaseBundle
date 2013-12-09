<?php

namespace RtxLabs\LiquibaseBundle\Runner;

use Doctrine\Bundle\DoctrineBundle\Registry;

use Symfony\Component\FileSystem\Filesystem;
use Symfony\Component\Process\Process;

class LiquibaseRunner
{
    private $filesystem;
    private $dbConnections;

    public function __construct(Filesystem $filesystem, Registry $doctrine)
    {
        $this->filesystem = $filesystem;

        foreach ($doctrine->getConnections() as $connectionName => $connection) {
            /** @var $connection \Doctrine\DBAL\Connection */
            $this->dbConnections[$connectionName] = $connection->getParams();
        }
    }

    public function runAppUpdate(\Symfony\Component\HttpKernel\KernelInterface $kernel)
    {
        $this->runUpdate($kernel->getRootDir().'/Resources/liquibase/changelog-{connection_name}.xml');
    }

    public function runBundleUpdate(\Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle)
    {
        $this->runUpdate($bundle->getPath().'/Resources/liquibase/changelog-{connection_name}.xml');
    }

    private function runUpdate($changelogFile)
    {
        foreach ($this->dbConnections as $connectionName => $connectionParams) {
            $changelogFileConn = str_replace('{connection_name}', $connectionName, $changelogFile);
            if (is_file($changelogFileConn)) {
                $command = $this->getBaseCommand($connectionParams);
                $command .= ' --changeLogFile=' . $changelogFileConn;
                $command .= " update";

                $this->run($command);
            }
        }
    }

    public function runRollback($bundle)
    {

    }

    public function runDiff($bundle)
    {

    }

    protected function run($command)
    {
        $output = "";
        exec($command, $output);

        echo $command."\n";
        print_r($output);
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
                $driver = "com.mysql.jdbc.Driver";
                break;
            default:
                throw new \RuntimeException("No JDBC-Driver found for $dbalDriver");
        }

        return $driver;
    }

    protected function getJdbcDriverClassPath($dbalDriver)
    {
        $dir = dirname(__FILE__) . "/../Resources/vendor/jdbc/";

        switch($dbalDriver) {
            case 'pdo_mysql':
            case 'mysql':
                $dir .= "mysql-connector-java-5.1.18-bin.jar";
                break;
            default:
                throw new \RuntimeException("No JDBC-Driver found for $dbalDriver");
        }

        return $dir;
    }

    protected function getJdbcDsn($dbalParams)
    {
        switch ($dbalParams['driver']) {
            case 'pdo_mysql':
                return $this->getMysqlJdbcDsn($dbalParams);
                break;
            default:
                throw new \RuntimeException("Database not supported");
        }
    }

    protected function getMysqlJdbcDsn($dbalParams)
    {
        $dsn = "jdbc:mysql://";
        if ($dbalParams['host'] != "") {
            $dsn .= $dbalParams['host'];
        }
        else {
            $dsn .= 'localhost';
        }

        if ($dbalParams['port'] != "") {
            $dsn .= ":" . $dbalParams['port'];
        }

        $dsn .= "/" . $dbalParams['dbname'] . '?useUnicode=true&characterEncoding=utf-8';
        if ('utf8' == strtolower($dbalParams['charset'])) {
            $dsn .= '?useUnicode=true&characterEncoding=utf-8';
        }

        return '"' . $dsn . '"';
    }
}
