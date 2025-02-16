<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\DoctrineSchema\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL57Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\DoctrineSchema\Builder\SchemaBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'ibexa:doctrine:schema:dump-sql')]
final class DumpSqlCommand extends Command
{
    private Connection $db;

    private SchemaBuilder $schemaBuilder;

    /**
     * @phpstan-var array<non-empty-string, class-string<\Doctrine\DBAL\Platforms\AbstractPlatform>>
     */
    private const PLATFORM_MAP = [
        'mysql8' => MySQL80Platform::class,
        'mysql57' => MySQL57Platform::class,
        'mysql' => MySqlPlatform::class,
        'mariadb' => MariaDb1027Platform::class,
        'postgres' => PostgreSQL100Platform::class,
    ];

    public function __construct(Connection $db, SchemaBuilder $schemaBuilder)
    {
        $this->db = $db;
        $this->schemaBuilder = $schemaBuilder;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::OPTIONAL
        );

        $this->addOption(
            'compare',
            null,
            InputOption::VALUE_NONE,
            'Compare against current database',
        );

        $this->addOption(
            'force-platform',
            null,
            InputOption::VALUE_REQUIRED,
            sprintf(
                'Provide a platform name to use. One of: "%s"',
                implode('","', array_keys(self::PLATFORM_MAP)),
            ),
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        if ($file !== null) {
            $toSchema = $this->schemaBuilder->importSchemaFromFile($file);
        } else {
            $toSchema = $this->schemaBuilder->buildSchema();
        }

        $platform = $this->getPlatformForInput($input);

        if ($input->getOption('compare')) {
            $schemaManager = $this->getSchemaManager();
            $fromSchema = $this->introspectSchema($schemaManager);

            $comparator = new Comparator();
            $diff = $comparator->compare($fromSchema, $toSchema);
            $sqls = $diff->toSql($platform);
        } else {
            $sqls = $toSchema->toSql($platform);
        }

        $io = new SymfonyStyle($input, $output);
        $io->getErrorStyle()->caution(
            [
                'This operation should not be executed in a production environment!',
                '',
                'Use the incremental update to detect changes during development and use',
                'the SQL DDL provided to manually update your database in production.',
                '',
            ]
        );

        foreach ($sqls as $sql) {
            $io->writeln($sql . ';');
        }

        return self::SUCCESS;
    }

    private function getSchemaManager(): AbstractSchemaManager
    {
        return $this->db->getSchemaManager();
    }

    private function introspectSchema(AbstractSchemaManager $schemaManager): Schema
    {
        return $schemaManager->createSchema();
    }

    private function getPlatformForInput(InputInterface $input): AbstractPlatform
    {
        $forcePlatform = $input->getOption('force-platform');

        if ($forcePlatform === null) {
            return $this->db->getDatabasePlatform();
        }

        if (!isset(self::PLATFORM_MAP[$forcePlatform])) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid --force-platform option. Received "%s", expected one of: "%s"',
                $forcePlatform,
                implode('","', array_keys(self::PLATFORM_MAP)),
            ));
        }

        $platformClass = self::PLATFORM_MAP[$forcePlatform];

        return new $platformClass();
    }
}
