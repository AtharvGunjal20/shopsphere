
<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = cfg('db');

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            // Configure SSL for the Aiven MySQL connection.
            $ca = dirname(__DIR__, 2) . '/config/ca.pem';

            if (str_starts_with($c['dsn'], 'mysql:') && is_file($ca)) {
                if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
                    $options[constant('PDO::MYSQL_ATTR_SSL_CA')] = $ca;
                }

                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')] = true;
                }
            }

            self::$pdo = new PDO(
                $c['dsn'],
                $c['user'],
                $c['pass'],
                $options
            );

            if (self::driver() === 'sqlite') {
                self::$pdo->exec('PRAGMA foreign_keys = ON');
            }
        }

        return self::$pdo;
    }

    public static function driver(): string
    {
        return self::pdo_driver();
    }

    private static function pdo_driver(): string
    {
        return self::$pdo
            ? self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)
            : 'mysql';
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    private static function ident(string $s): string
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $s)) {
            throw new \InvalidArgumentException('Bad identifier: ' . $s);
        }

        return $s;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_map([self::class, 'ident'], array_keys($data));

        $sql = 'INSERT INTO ' . self::ident($table)
            . ' (' . implode(',', $cols) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';

        self::q($sql, array_values($data));

        return (int) self::pdo()->lastInsertId();
    }

    /** $where is a SQL fragment with ? placeholders, e.g. "id = ?" */
    public static function update(
        string $table,
        array $data,
        string $where,
        array $whereParams = []
    ): int {
        $sets = array_map(
            fn($c) => self::ident($c) . ' = ?',
            array_keys($data)
        );

        $sql = 'UPDATE ' . self::ident($table)
            . ' SET ' . implode(', ', $sets)
            . ' WHERE ' . $where;

        return self::exec(
            $sql,
            [...array_values($data), ...$whereParams]
        );
    }

    /** Row lock suffix; only meaningful on MySQL/InnoDB. */
    public static function forUpdate(): string
    {
        return self::driver() === 'mysql' ? ' FOR UPDATE' : '';
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();

        if ($pdo->inTransaction()) {
            return $fn();
        }

        $pdo->beginTransaction();

        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
}
