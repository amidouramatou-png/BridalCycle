<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/settings.php';
require_once dirname(__DIR__) . '/core/db_class.php';
date_default_timezone_set(APP_TIMEZONE);

// Small helper for the tests only: run SQL to prepare and clean the test data
class TestDatabase extends Database
{
    public function exec(string $sql, string $types = '', mixed ...$params): void
    {
        $this->run($sql, $types, ...$params)->close();
    }

    public function one(string $sql, string $types = '', mixed ...$params): ?array
    {
        return $this->fetchOne($sql, $types, ...$params);
    }
}
