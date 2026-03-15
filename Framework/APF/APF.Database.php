<?php
/**
 * Adlaire Platform Foundation (APF) - Database Module
 *
 * APF = Adlaire Platform Foundation
 *
 * @package APF
 * @version 1.0.0
 * @license Adlaire License Ver.2.0
 */

namespace APF\Database;

// ============================================================================
// Connection - データベース接続
// ============================================================================

class Connection {
    private ?\PDO $pdo = null;
    private array $config;
    private array $queries = [];
    private bool $logging = false;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function connect(): \PDO {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $driver = $this->config['driver'] ?? 'mysql';
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 3306;
        $database = $this->config['database'] ?? '';
        $username = $this->config['username'] ?? '';
        $password = $this->config['password'] ?? '';
        $charset = $this->config['charset'] ?? 'utf8mb4';
        $options = $this->config['options'] ?? [];

        $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

        $defaultOptions = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $this->pdo = new \PDO($dsn, $username, $password, array_merge($defaultOptions, $options));
        
        return $this->pdo;
    }

    public function query(string $sql, array $bindings = []): array {
        $stmt = $this->execute($sql, $bindings);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $bindings = []): ?array {
        $stmt = $this->execute($sql, $bindings);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function execute(string $sql, array $bindings = []): \PDOStatement {
        $pdo = $this->connect();
        
        $start = microtime(true);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bindings);
        $time = microtime(true) - $start;

        if ($this->logging) {
            $this->queries[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'time' => $time
            ];
        }

        return $stmt;
    }

    public function insert(string $sql, array $bindings = []): int {
        $this->execute($sql, $bindings);
        return (int)$this->connect()->lastInsertId();
    }

    public function update(string $sql, array $bindings = []): int {
        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount();
    }

    public function delete(string $sql, array $bindings = []): int {
        $stmt = $this->execute($sql, $bindings);
        return $stmt->rowCount();
    }

    /** @var int ネストされたトランザクションの深度 @since Ver.1.9 */
    private int $transactionDepth = 0;

    /**
     * @updated Ver.1.9 ネストトランザクション対応（SAVEPOINT）
     */
    public function transaction(\Closure $callback): mixed {
        $pdo = $this->connect();

        if ($this->transactionDepth === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT sp_{$this->transactionDepth}");
        }
        $this->transactionDepth++;

        try {
            $result = $callback($this);
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                $pdo->commit();
            } else {
                $pdo->exec("RELEASE SAVEPOINT sp_{$this->transactionDepth}");
            }
            return $result;
        } catch (\Throwable $e) {
            $this->transactionDepth--;
            if ($this->transactionDepth === 0) {
                $pdo->rollBack();
            } else {
                $pdo->exec("ROLLBACK TO SAVEPOINT sp_{$this->transactionDepth}");
            }
            \APF\Utilities\Logger::error('Transaction rolled back', [
                'depth'   => $this->transactionDepth,
                'error'   => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * 現在のトランザクション深度を返す。
     * @since Ver.1.9
     */
    public function transactionDepth(): int {
        return $this->transactionDepth;
    }

    public function enableQueryLog(): void {
        $this->logging = true;
    }

    public function getQueryLog(): array {
        return $this->queries;
    }

    public function getPdo(): \PDO {
        return $this->connect();
    }
}

// ============================================================================
// QueryBuilder - クエリビルダー
// ============================================================================

class QueryBuilder {
    private readonly Connection $connection;
    private string $table = '';
    private array $select = ['*'];
    private array $where = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orderBy = [];
    private array $groupBy = [];
    private ?string $having = null;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    public function table(string $table): self {
        $this->table = $table;
        return $this;
    }

    public function select(...$columns): self {
        $this->select = empty($columns) ? ['*'] : $columns;
        return $this;
    }

    public function where(string $column, $operator, $value = null): self {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];

        $this->bindings[] = $value;
        return $this;
    }

    public function orWhere(string $column, $operator, $value = null): self {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'OR',
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];

        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values): self {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        
        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IN',
            'value' => $placeholders
        ];

        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    public function whereNull(string $column): self {
        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null
        ];
        return $this;
    }

    public function whereNotNull(string $column): self {
        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IS NOT NULL',
            'value' => null
        ];
        return $this;
    }

    /**
     * BETWEEN 条件を追加する。
     * @since Ver.1.9
     */
    public function whereBetween(string $column, array $range): self {
        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'BETWEEN',
            'value' => null,
        ];
        $this->bindings[] = $range[0];
        $this->bindings[] = $range[1];
        return $this;
    }

    /**
     * NOT BETWEEN 条件を追加する。
     * @since Ver.1.9
     */
    public function whereNotBetween(string $column, array $range): self {
        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'NOT BETWEEN',
            'value' => null,
        ];
        $this->bindings[] = $range[0];
        $this->bindings[] = $range[1];
        return $this;
    }

    /**
     * LIKE 条件を追加する。
     * @since Ver.1.9
     */
    public function whereLike(string $column, string $pattern): self {
        return $this->where($column, 'LIKE', $pattern);
    }

    public function join(string $table, string $first, string $operator, string $second): self {
        $this->joins[] = [
            'type' => 'INNER',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second
        ];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self {
        $dir = strtoupper($direction);
        if (!in_array($dir, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Invalid ORDER BY direction: {$direction}");
        }
        $this->orderBy[] = [$column, $dir];
        return $this;
    }

    public function groupBy(...$columns): self {
        $this->groupBy = $columns;
        return $this;
    }

    /**
     * HAVING 条件を設定する。
     * @updated Ver.1.9 プレースホルダバインディング対応
     */
    public function having(string $condition, array $bindings = []): self {
        $this->having = $condition;
        if (!empty($bindings)) {
            $this->bindings = array_merge($this->bindings, $bindings);
        }
        return $this;
    }

    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array {
        $sql = $this->toSql();
        return $this->connection->query($sql, $this->bindings);
    }

    public function first(): ?array {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function count(): int {
        $original = $this->select;
        $this->select = ['COUNT(*) as count'];
        
        $result = $this->first();
        $this->select = $original;
        
        return (int)($result['count'] ?? 0);
    }

    public function exists(): bool {
        return $this->count() > 0;
    }

    /**
     * ページネーション付きで結果を取得する。
     * @since Ver.1.9
     * @return array{data: array, total: int, page: int, per_page: int, total_pages: int}
     */
    public function paginate(int $page = 1, int $perPage = 15): array {
        $total = (clone $this)->count();
        $data  = $this->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return [
            'data'        => $data,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
        ];
    }

    /**
     * 複数行を一括挿入する。
     * @since Ver.1.9
     * @return int 挿入された行数
     */
    public function insertBatch(array $records): int {
        if (empty($records)) return 0;

        $columns = array_keys($records[0]);
        $rowPlaceholder = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($records), $rowPlaceholder));
        $allValues = [];
        foreach ($records as $record) {
            foreach ($columns as $col) {
                $allValues[] = $record[$col] ?? null;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->table,
            implode(', ', $columns),
            $allPlaceholders
        );

        $this->connection->execute($sql, $allValues);
        return count($records);
    }

    public function insert(array $data): int {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($data), '?');
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        return $this->connection->insert($sql, array_values($data));
    }

    public function insertGetId(array $data): int {
        return $this->insert($data);
    }

    public function update(array $data): int {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = "{$column} = ?";
        }

        $sql = sprintf('UPDATE %s SET %s', $this->table, implode(', ', $sets));
        
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . $this->buildWhere();
        }

        return $this->connection->update($sql, array_merge(array_values($data), $this->bindings));
    }

    public function delete(): int {
        $sql = sprintf('DELETE FROM %s', $this->table);
        
        if (!empty($this->where)) {
            $sql .= ' WHERE ' . $this->buildWhere();
        }

        return $this->connection->delete($sql, $this->bindings);
    }

    public function toSql(): string {
        $sql = sprintf(
            'SELECT %s FROM %s',
            implode(', ', $this->select),
            $this->table
        );

        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= sprintf(
                    ' %s JOIN %s ON %s %s %s',
                    $join['type'],
                    $join['table'],
                    $join['first'],
                    $join['operator'],
                    $join['second']
                );
            }
        }

        if (!empty($this->where)) {
            $sql .= ' WHERE ' . $this->buildWhere();
        }

        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if ($this->having !== null) {
            $sql .= ' HAVING ' . $this->having;
        }

        if (!empty($this->orderBy)) {
            $orders = array_map(fn($order) => "{$order[0]} {$order[1]}", $this->orderBy);
            $sql .= ' ORDER BY ' . implode(', ', $orders);
        }

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * @updated Ver.1.9 BETWEEN / NOT BETWEEN サポート追加
     */
    private function buildWhere(): string {
        $conditions = [];

        foreach ($this->where as $index => $where) {
            $prefix = $index === 0 ? '' : " {$where['type']} ";

            $conditions[] = match (true) {
                $where['operator'] === 'IN'
                    => $prefix . "{$where['column']} IN ({$where['value']})",
                $where['operator'] === 'IS NULL' || $where['operator'] === 'IS NOT NULL'
                    => $prefix . "{$where['column']} {$where['operator']}",
                $where['operator'] === 'BETWEEN' || $where['operator'] === 'NOT BETWEEN'
                    => $prefix . "{$where['column']} {$where['operator']} ? AND ?",
                default
                    => $prefix . "{$where['column']} {$where['operator']} ?",
            };
        }

        return implode('', $conditions);
    }
}

// ============================================================================
// Model - ORM基底クラス
// ============================================================================

abstract class Model {
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected static array $fillable = [];
    protected static array $hidden = [];
    protected static Connection $connection;

    protected array $attributes = [];
    protected array $original = [];
    protected bool $exists = false;

    public function __construct(array $attributes = []) {
        $this->fill($attributes);
    }

    public static function setConnection(Connection $connection): void {
        static::$connection = $connection;
    }

    protected static function query(): QueryBuilder {
        return (new QueryBuilder(static::$connection))->table(static::getTableName());
    }

    protected static function getTableName(): string {
        if (!empty(static::$table)) {
            return static::$table;
        }

        $class = basename(str_replace('\\', '/', static::class));
        return strtolower($class) . 's';
    }

    /**
     * @updated Ver.1.9 デフォルト LIMIT 追加（OOM 防止）
     */
    public static function all(int $limit = 1000): array {
        $query = static::query();
        if ($limit > 0) {
            $query->limit($limit);
        }
        $results = $query->get();
        return array_map(fn($data) => new static($data), $results);
    }

    public static function find($id): ?static {
        $data = static::query()->where(static::$primaryKey, $id)->first();
        
        if ($data === null) {
            return null;
        }

        $model = new static($data);
        $model->exists = true;
        $model->original = $data;
        return $model;
    }

    public static function where(string $column, $operator, $value = null): QueryBuilder {
        return static::query()->where($column, $operator, $value);
    }

    public static function create(array $attributes): static {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    public function save(): bool {
        if ($this->exists) {
            return $this->performUpdate();
        }

        return $this->performInsert();
    }

    protected function performInsert(): bool {
        $data = $this->getFillableAttributes();
        $id = static::query()->insert($data);
        
        $this->setAttribute(static::$primaryKey, $id);
        $this->exists = true;
        $this->original = $this->attributes;
        
        return true;
    }

    protected function performUpdate(): bool {
        $dirty = $this->getDirty();
        
        if (empty($dirty)) {
            return true;
        }

        $id = $this->getAttribute(static::$primaryKey);
        $affected = static::query()
            ->where(static::$primaryKey, $id)
            ->update($dirty);
        
        $this->original = $this->attributes;
        
        return $affected > 0;
    }

    public function delete(): bool {
        if (!$this->exists) {
            return false;
        }

        $id = $this->getAttribute(static::$primaryKey);
        $deleted = static::query()
            ->where(static::$primaryKey, $id)
            ->delete();
        
        $this->exists = false;
        
        return $deleted > 0;
    }

    public function fill(array $attributes): void {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
    }

    protected function isFillable(string $key): bool {
        return empty(static::$fillable) || in_array($key, static::$fillable);
    }

    protected function getFillableAttributes(): array {
        if (empty(static::$fillable)) {
            return $this->attributes;
        }

        return array_filter(
            $this->attributes,
            fn($key) => in_array($key, static::$fillable),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function getDirty(): array {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    public function toArray(): array {
        $data = $this->attributes;

        foreach (static::$hidden as $key) {
            unset($data[$key]);
        }

        return $data;
    }

    public function toJson(): string {
        return json_encode($this->toArray());
    }

    public function setAttribute(string $key, $value): void {
        $this->attributes[$key] = $value;
    }

    public function getAttribute(string $key): mixed {
        return $this->attributes[$key] ?? null;
    }

    public function __get(string $key): mixed {
        return $this->getAttribute($key);
    }

    public function __set(string $key, $value): void {
        $this->setAttribute($key, $value);
    }

    public function __isset(string $key): bool {
        return isset($this->attributes[$key]);
    }
}

// ============================================================================
// Schema - スキーマビルダー
// ============================================================================

class Schema {
    private Connection $connection;

    public function __construct(Connection $connection) {
        $this->connection = $connection;
    }

    public function create(string $table, \Closure $callback): void {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        
        $sql = $blueprint->toSql();
        $this->connection->execute($sql);
    }

    public function drop(string $table): void {
        $sql = "DROP TABLE IF EXISTS {$table}";
        $this->connection->execute($sql);
    }

    public function hasTable(string $table): bool {
        $result = $this->connection->queryOne(
            "SHOW TABLES LIKE ?",
            [$table]
        );
        
        return $result !== null;
    }
}

class Blueprint {
    private string $table;
    private array $columns = [];

    public function __construct(string $table) {
        $this->table = $table;
    }

    public function id(): void {
        $this->columns[] = "id INT AUTO_INCREMENT PRIMARY KEY";
    }

    public function string(string $name, int $length = 255): void {
        $this->columns[] = "{$name} VARCHAR({$length})";
    }

    public function text(string $name): void {
        $this->columns[] = "{$name} TEXT";
    }

    public function integer(string $name): void {
        $this->columns[] = "{$name} INT";
    }

    public function bigInteger(string $name): void {
        $this->columns[] = "{$name} BIGINT";
    }

    public function float(string $name): void {
        $this->columns[] = "{$name} FLOAT";
    }

    public function double(string $name): void {
        $this->columns[] = "{$name} DOUBLE";
    }

    public function decimal(string $name, int $total = 8, int $places = 2): void {
        $this->columns[] = "{$name} DECIMAL({$total},{$places})";
    }

    public function boolean(string $name): void {
        $this->columns[] = "{$name} TINYINT(1)";
    }

    public function date(string $name): void {
        $this->columns[] = "{$name} DATE";
    }

    public function datetime(string $name): void {
        $this->columns[] = "{$name} DATETIME";
    }

    public function timestamp(string $name): void {
        $this->columns[] = "{$name} TIMESTAMP";
    }

    public function timestamps(): void {
        $this->columns[] = "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
    }

    public function toSql(): string {
        $columns = implode(', ', $this->columns);
        return "CREATE TABLE {$this->table} ({$columns})";
    }
}
