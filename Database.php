<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function buildQuery(string $query, array $args = []): string
    {
        $argIndex = 0;
        $query = preg_replace_callback('/\?(\w?)/', function ($matches) use (&$args, &$argIndex) {
            $type = $matches[1];
            $value = $args[$argIndex++] ?? null;

            switch ($type) {
                case 'd':
                    return intval($value);
                case 'f':
                    return floatval($value);
                case 'a':
                    return $this->formatArray($value);
                case '#':
                    return $this->formatIdentifiers($value);
                default:
                    return $this->escapeValue($value);
            }
        }, $query);

        $query = $this->processConditionalBlocks($query, $args);

        return $query;
    }

    private function escapeValue($value)
    {
        if (is_null($value)) {
            return 'NULL';
        } elseif (is_bool($value)) {
            return $value ? '1' : '0';
        } elseif (is_string($value)) {
            return '\'' . $this->mysqli->real_escape_string($value) . '\'';
        }
        return $value;
    }

    private function formatArray(array $arr): string
    {
        $result = array_map(function ($item) {
            return $this->escapeValue($item);
        }, $arr);

        return implode(', ', $result);
    }

    private function formatIdentifiers($value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(function ($item) {
                return '`' . $this->escapeIdentifier($item) . '`';
            }, $value));
        } else {
            return '`' . $this->escapeIdentifier($value) . '`';
        }
    }

    private function escapeIdentifier($identifier): string
    {
        return str_replace('`', '``', $identifier);
    }

    private function processConditionalBlocks(string $query, array $args): string
    {
        return preg_replace_callback('/\{([^\{\}]*)\}/', function ($matches) use ($args) {
            $block = $matches[1];
            foreach ($args as $arg) {
                if ($arg === $this->skip()) {
                    return '';
                }
            }
            return $block;
        }, $query);
    }

    public function skip()
    {
        return 'FPDBTEST_SKIP_PLACEHOLDER';
    }
}

