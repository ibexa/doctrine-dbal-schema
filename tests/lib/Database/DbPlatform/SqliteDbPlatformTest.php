<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\DoctrineSchema\Database\DbPlatform;

use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Ibexa\DoctrineSchema\Database\DbPlatform\SqliteDbPlatform;
use Ibexa\Tests\DoctrineSchema\Database\TestDatabaseFactory;
use PHPUnit\Framework\TestCase;

class SqliteDbPlatformTest extends TestCase
{
    /** @var \Ibexa\Tests\DoctrineSchema\Database\TestDatabaseFactory */
    private $testDatabaseFactory;

    /** @var \Ibexa\DoctrineSchema\Database\DbPlatform\SqliteDbPlatform */
    private $sqliteDbPlatform;

    public function setUp(): void
    {
        $this->sqliteDbPlatform = new SqliteDbPlatform();
        $this->testDatabaseFactory = new TestDatabaseFactory();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     * @throws \Ibexa\Tests\DoctrineSchema\Database\TestDatabaseConfigurationException
     */
    public function testForeignKeys(): void
    {
        $connection = $this->testDatabaseFactory->prepareAndConnect($this->sqliteDbPlatform);
        $schema = $connection->createSchemaManager()->introspectSchema();

        $primaryTable = $schema->createTable('my_primary_table');
        $primaryTable->addColumn('id', 'integer');
        $primaryTable->setPrimaryKey(['id']);

        $secondaryTable = $schema->createTable('my_secondary_table');
        $secondaryTable->addColumn('id', 'integer');
        $secondaryTable->setPrimaryKey(['id']);
        $secondaryTable->addForeignKeyConstraint($primaryTable, ['id'], ['id']);

        // persist table structure
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $query) {
            $connection->executeUpdate($query);
        }

        $connection->insert($primaryTable->getName(), ['id' => 1], [ParameterType::INTEGER]);
        $connection->insert($secondaryTable->getName(), ['id' => 1], [ParameterType::INTEGER]);

        // insert broken record
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('FOREIGN KEY constraint failed');
        $connection->insert($secondaryTable->getName(), ['id' => 2], [ParameterType::INTEGER]);
    }
}

class_alias(SqliteDbPlatformTest::class, 'EzSystems\Tests\DoctrineSchema\Database\DbPlatform\SqliteDbPlatformTest');
