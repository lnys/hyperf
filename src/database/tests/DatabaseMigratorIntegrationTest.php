<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
namespace HyperfTest\Database;

use Hyperf\Database\ConnectionResolver;
use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Database\Connectors\ConnectionFactory;
use Hyperf\Database\Connectors\MySqlConnector;
use Hyperf\Database\Migrations\DatabaseMigrationRepository;
use Hyperf\Database\Migrations\Migrator;
use Hyperf\Database\Schema\Schema;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Filesystem\Filesystem;
use Hyperf\Utils\Str;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Style\OutputStyle;

/**
 * @internal
 * @coversNothing
 */
class DatabaseMigratorIntegrationTest extends TestCase
{
    protected $migrator;

    protected function setUp(): void
    {
        $container = m::mock(ContainerInterface::class);
        $container->shouldReceive('has')->andReturn(true);
        $container->shouldReceive('get')->with('db.connector.mysql')->andReturn(new MySqlConnector());
        $connector = new ConnectionFactory($container);

        $dbConfig = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'hyperf',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
        ];

        $connection = $connector->make($dbConfig);

        $resolver = new ConnectionResolver(['default' => $connection]);

        $container->shouldReceive('get')->with(ConnectionResolverInterface::class)->andReturn($resolver);

        ApplicationContext::setContainer($container);

        $this->migrator = new Migrator(
            $repository = new DatabaseMigrationRepository($resolver, 'migrations'),
            $resolver,
            new Filesystem()
        );

        $output = m::mock(OutputStyle::class);
        $output->shouldReceive('writeln');

        $this->migrator->setOutput($output);

        if (! $repository->repositoryExists()) {
            $repository->createRepository();
        }
    }

    public function testCreateTableforMigration()
    {
        $schema = new Schema();

        $this->migrator->rollback([__DIR__ . '/migrations/one']);
        $this->migrator->run([__DIR__ . '/migrations/one']);

        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));

        $res = (array) $schema->connection()->selectOne('SHOW CREATE TABLE users;');
        $sql = $res['Create Table'];
        $asserts = [
            "CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='Users Table'",
            "CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci COMMENT='Users Table'",
        ];

        $this->assertTrue(in_array($sql, $asserts, true));

        $res = (array) $schema->connection()->selectOne('SHOW CREATE TABLE password_resets;');
        $sql = $res['Create Table'];
        $asserts = [
            'CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `password_resets_email_index` (`email`),
  KEY `password_resets_token_index` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci',
            'CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL,
  KEY `password_resets_email_index` (`email`),
  KEY `password_resets_token_index` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8_unicode_ci',
        ];
        $this->assertTrue(in_array($sql, $asserts, true));
    }

    public function testBasicMigrationOfSingleFolder()
    {
        $schema = new Schema();

        $this->migrator->rollback([__DIR__ . '/migrations/one']);

        $ran = $this->migrator->run([__DIR__ . '/migrations/one']);

        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));

        $this->assertTrue(Str::contains($ran[0], 'users'));
        $this->assertTrue(Str::contains($ran[1], 'password_resets'));
    }

    public function testMigrationsCanBeRolledBack()
    {
        $schema = new Schema();
        $this->migrator->run([__DIR__ . '/migrations/one']);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));
        $rolledBack = $this->migrator->rollback([__DIR__ . '/migrations/one']);
        $this->assertFalse($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('password_resets'));

        $this->assertTrue(Str::contains($rolledBack[0], 'password_resets'));
        $this->assertTrue(Str::contains($rolledBack[1], 'users'));
    }

    public function testMigrationsCanBeReset()
    {
        $schema = new Schema();
        $this->migrator->run([__DIR__ . '/migrations/one']);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));
        $rolledBack = $this->migrator->reset([__DIR__ . '/migrations/one']);
        $this->assertFalse($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('password_resets'));

        $this->assertTrue(Str::contains($rolledBack[0], 'password_resets'));
        $this->assertTrue(Str::contains($rolledBack[1], 'users'));
    }

    public function testNoErrorIsThrownWhenNoOutstandingMigrationsExist()
    {
        $schema = new Schema();
        $this->migrator->run([__DIR__ . '/migrations/one']);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));
        $this->migrator->run([__DIR__ . '/migrations/one']);
    }

    public function testNoErrorIsThrownWhenNothingToRollback()
    {
        $schema = new Schema();
        $this->migrator->run([__DIR__ . '/migrations/one']);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));
        $this->migrator->rollback([__DIR__ . '/migrations/one']);
        $this->assertFalse($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('password_resets'));
        $this->migrator->rollback([__DIR__ . '/migrations/one']);
    }

    public function testMigrationsCanRunAcrossMultiplePaths()
    {
        $schema = new Schema();
        $this->migrator->run([__DIR__ . '/migrations/one', __DIR__ . '/migrations/two']);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));
        $this->assertTrue($schema->hasTable('flights'));
    }

    public function testMigrationsCanBeRolledBackAcrossMultiplePaths()
    {
        $schema = new Schema();
        $this->migrator->run([__DIR__ . '/migrations/one', __DIR__ . '/migrations/two']);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));
        $this->assertTrue($schema->hasTable('flights'));
        $this->migrator->rollback([__DIR__ . '/migrations/one', __DIR__ . '/migrations/two']);
        $this->assertFalse($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('password_resets'));
        $this->assertFalse($schema->hasTable('flights'));
    }

    public function testMigrationsCanBeResetAcrossMultiplePaths()
    {
        $schema = new Schema();
        $this->migrator->run([__DIR__ . '/migrations/one', __DIR__ . '/migrations/two']);
        $this->assertTrue($schema->hasTable('users'));
        $this->assertTrue($schema->hasTable('password_resets'));
        $this->assertTrue($schema->hasTable('flights'));
        $this->migrator->reset([__DIR__ . '/migrations/one', __DIR__ . '/migrations/two']);
        $this->assertFalse($schema->hasTable('users'));
        $this->assertFalse($schema->hasTable('password_resets'));
        $this->assertFalse($schema->hasTable('flights'));
    }
}
