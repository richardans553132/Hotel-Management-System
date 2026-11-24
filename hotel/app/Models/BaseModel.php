<?php
namespace App\Models;

use App\Helpers\Database;
use PDO;

/**
 * BaseModel provides generic CRUD operations for all models.
 * Child classes only need to define a protected static $table
 * property and optionally a $primaryKey (default 'id').
 */
class BaseModel
{
    protected static string $table;
    protected static string $primaryKey = 'id';
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    // ---------- CREATE ----------
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":$c", $columns);
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            static::$table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    // ---------- READ ----------
    public function find(int $id): ?array
    {
        $sql = sprintf("SELECT * FROM %s WHERE %s = :id", static::$table, static::$primaryKey);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query("SELECT * FROM " . static::$table);
        return $stmt->fetchAll();
    }

    // ---------- UPDATE ----------
    public function update(int $id, array $data): bool
    {
        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "$col = :$col";
        }
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :id",
            static::$table,
            implode(', ', $set),
            static::$primaryKey
        );
        $data['id'] = $id;
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($data);
    }

    // ---------- DELETE ----------
    public function delete(int $id): bool
    {
        $sql = sprintf("DELETE FROM %s WHERE %s = :id", static::$table, static::$primaryKey);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }
}
?>
