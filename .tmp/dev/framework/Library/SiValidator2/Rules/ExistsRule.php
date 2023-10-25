<?php

namespace SiValidator2\Rules;

use framework\SpiralConnecter\SpiralDB;
use SiValidator2\Rule;

class ExistsRule implements RuleInterface
{
    protected $table;
    protected $column;
    protected $wheres = [];

    public function __construct(string $table, string $column = null)
    {
        $this->table = $table;
        $this->column = $column;
    }

    public static function processable($value): bool
    {
        return true;  // Always process this rule
    }

    public function where(callable $callback)
    {
        $this->wheres[] = $callback;
        return $this;
    }

    public function validate($value, array $allValues = []): bool
    {
        // Eloquentモデルのクエリを作成
        $query = SpiralDB::title($this->table);

        // カラムが指定されていない場合は、フィールド名をカラムとして使用
        $column = $this->column;

        // クエリの実行
        $query->where($column, $value);

        // 追加のwhere条件を適用
        foreach ($this->wheres as $where) {
            $query = $where($query);
        }

        $data = $query->get();

        return $data->count() > 0;
    }

    public function message(): string
    {
        // This rule won't produce an error message since it just excludes the field
        return "";
    }

    public function name(): string
    {
        return 'exists';
    }
}
