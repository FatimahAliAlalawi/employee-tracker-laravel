<?php

use Illuminate\Support\Facades\DB;

/**
 * بديل $wpdb مبني على اتصال قاعدة بيانات Laravel (PDO). يوفّر فقط الدوال التي يستخدمها
 * منطق النظام: prepare / get_results / get_row / get_var / get_col / insert / update / delete / query.
 * الاستعلامات تُبنى بنفس أسلوب الكود الأصلي، و prepare() يهرّب القيم عبر PDO::quote.
 */
class Wpdb
{
    public string $prefix = '';
    public int $insert_id = 0;
    public int $rows_affected = 0;
    public string $last_error = '';
    public string $last_query = '';

    public function __construct(?string $prefix = null)
    {
        $this->prefix = $prefix ?? (string) config('tracker.table_prefix', '');
    }

    private function pdo(): \PDO
    {
        return DB::connection()->getPdo();
    }

    public function esc_like($text): string
    {
        return addcslashes((string) $text, '_%\\');
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    /** نفس سلوك $wpdb->prepare: %s %d %f %i (+ الصيغ المرقّمة %1$s)، و %% حرف نسبة */
    public function prepare($query, ...$args)
    {
        if ($query === null) {
            return null;
        }
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        $args = array_values($args);
        $i = 0;
        $pdo = $this->pdo();

        return preg_replace_callback('/%(?:(\d+)\$)?([sdfFi%])/', function ($m) use (&$i, $args, $pdo) {
            if ($m[2] === '%') {
                return '%';
            }
            if ($m[1] !== '') {
                $value = $args[(int) $m[1] - 1] ?? null;
            } else {
                $value = $args[$i] ?? null;
                $i++;
            }
            switch ($m[2]) {
                case 'd':
                    return (string) (int) $value;
                case 'f':
                case 'F':
                    return (string) (float) $value;
                case 'i':
                    return '`' . str_replace('`', '``', (string) $value) . '`';
                default:
                    return $pdo->quote((string) $value);
            }
        }, $query);
    }

    private function run($query): array
    {
        $this->last_query = (string) $query;
        $this->last_error = '';
        try {
            $rows = DB::select($query);
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            report($e);
            return [];
        }
        // $wpdb يعيد كل القيم كنصوص (أو null) — منطق النظام مبني على ذلك (مقارنات مثل === '1')
        foreach ($rows as $row) {
            foreach ($row as $k => $v) {
                if ($v !== null && ! is_string($v)) {
                    $row->$k = (string) $v;
                }
            }
        }
        return $rows;
    }

    public function get_results($query, $output = 'OBJECT')
    {
        $rows = $this->run($query);
        if ($output === 'ARRAY_A') {
            return array_map(fn ($r) => (array) $r, $rows);
        }
        if ($output === 'ARRAY_N') {
            return array_map(fn ($r) => array_values((array) $r), $rows);
        }
        return $rows;
    }

    public function get_row($query, $output = 'OBJECT', $y = 0)
    {
        $rows = $this->run($query);
        if (! isset($rows[$y])) {
            return null;
        }
        if ($output === 'ARRAY_A') {
            return (array) $rows[$y];
        }
        if ($output === 'ARRAY_N') {
            return array_values((array) $rows[$y]);
        }
        return $rows[$y];
    }

    public function get_var($query, $x = 0, $y = 0)
    {
        $rows = $this->run($query);
        if (! isset($rows[$y])) {
            return null;
        }
        $values = array_values((array) $rows[$y]);
        return isset($values[$x]) ? (string) $values[$x] : null;
    }

    public function get_col($query, $x = 0): array
    {
        $rows = $this->run($query);
        $out = [];
        foreach ($rows as $row) {
            $values = array_values((array) $row);
            $out[] = isset($values[$x]) ? (string) $values[$x] : null;
        }
        return $out;
    }

    /** يعيد عدد الصفوف المتأثرة، أو false عند الخطأ (مثل ووردبريس) */
    public function query($query)
    {
        $this->last_query = (string) $query;
        $this->last_error = '';
        try {
            $trim = ltrim((string) $query);
            if (preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $trim)) {
                $rows = DB::select($query);
                $this->rows_affected = count($rows);
                return $this->rows_affected;
            }
            if (preg_match('/^(INSERT|REPLACE)\b/i', $trim)) {
                $this->rows_affected = DB::affectingStatement($query);
                $this->insert_id = (int) $this->pdo()->lastInsertId();
                return $this->rows_affected;
            }
            if (preg_match('/^(UPDATE|DELETE)\b/i', $trim)) {
                $this->rows_affected = DB::affectingStatement($query);
                return $this->rows_affected;
            }
            DB::unprepared($query);
            return true;
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            report($e);
            return false;
        }
    }

    private function cast(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_bool($v)) {
                $data[$k] = (int) $v;
            }
        }
        return $data;
    }

    public function insert($table, $data, $format = null)
    {
        try {
            $id = DB::table($table)->insertGetId($this->cast((array) $data));
            $this->insert_id = (int) $id;
            $this->rows_affected = 1;
            return 1;
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            report($e);
            return false;
        }
    }

    public function replace($table, $data, $format = null)
    {
        $data = $this->cast((array) $data);
        $cols = implode(',', array_map(fn ($c) => '`' . $c . '`', array_keys($data)));
        $vals = implode(',', array_map(fn ($v) => $v === null ? 'NULL' : $this->pdo()->quote((string) $v), $data));
        return $this->query("REPLACE INTO `{$table}` ({$cols}) VALUES ({$vals})");
    }

    public function update($table, $data, $where, $format = null, $where_format = null)
    {
        try {
            $q = DB::table($table);
            foreach ((array) $where as $col => $val) {
                $val === null ? $q->whereNull($col) : $q->where($col, $val);
            }
            $this->rows_affected = $q->update($this->cast((array) $data));
            return $this->rows_affected;
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            report($e);
            return false;
        }
    }

    public function delete($table, $where, $where_format = null)
    {
        try {
            $q = DB::table($table);
            foreach ((array) $where as $col => $val) {
                $val === null ? $q->whereNull($col) : $q->where($col, $val);
            }
            $this->rows_affected = $q->delete();
            return $this->rows_affected;
        } catch (\Throwable $e) {
            $this->last_error = $e->getMessage();
            report($e);
            return false;
        }
    }
}
