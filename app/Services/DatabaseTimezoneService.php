<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\DetectsLostConnections;
use PDO;
use PDOException;
use WeakMap;

class DatabaseTimezoneService
{
    use DetectsLostConnections;

    private WeakMap $offsets;

    private WeakMap $connections;

    public function __construct()
    {
        $this->offsets = new WeakMap;
        $this->connections = new WeakMap;
    }

    public function configure(Connection $connection): void
    {
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }
        $pdo = $connection->getRawPdo();
        if ($pdo instanceof Closure) {
            $connection->setPdo($this->wrap($pdo));
        } elseif ($pdo instanceof PDO) {
            $this->synchronize($pdo);
        }
        $connection->setReadPdo($this->wrap($connection->getRawReadPdo()));
        if (isset($this->connections[$connection])) {
            return;
        }
        $this->connections[$connection] = true;
        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            foreach ([$connection->getRawPdo(), $connection->getRawReadPdo()] as $pdo) {
                if ($pdo instanceof PDO) {
                    try {
                        $this->synchronize($pdo);
                    } catch (PDOException $exception) {
                        // Let the business query invoke Laravel's normal lost-connection recovery.
                        if (! $this->causedByLostConnection($exception)) {
                            throw $exception;
                        }
                    }
                }
            }
        });
    }

    private function wrap(PDO|Closure|null $pdo): PDO|Closure|null
    {
        if ($pdo instanceof Closure) {
            return function () use ($pdo): PDO {
                $connection = $pdo();
                $this->synchronize($connection);

                return $connection;
            };
        }
        if ($pdo instanceof PDO) {
            $this->synchronize($pdo);
        }

        return $pdo;
    }

    private function synchronize(PDO $pdo): void
    {
        $offset = CarbonImmutable::now(config('app.timezone'))->format('P');
        if (($this->offsets[$pdo] ?? null) !== $offset) {
            $pdo->exec("SET SESSION time_zone = '{$offset}'");
            $this->offsets[$pdo] = $offset;
        }
    }
}
