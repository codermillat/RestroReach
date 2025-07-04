<?php
/**
 * Database Utilities Class
 *
 * Centralized database operations and utilities for the Restaurant Delivery Manager
 * Consolidates common database patterns to reduce code duplication
 *
 * @package RestaurantDeliveryManager
 * @subpackage Database
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * RDM_Database_Utilities class
 *
 * Provides centralized database utilities and common operations
 * to reduce code duplication across the plugin
 *
 * @since 1.0.0
 */
class RDM_Database_Utilities {

    /**
     * Database instance
     *
     * @var RDM_Database
     */
    private static $database = null;

    /**
     * Get database instance
     *
     * @since 1.0.0
     * @return RDM_Database
     */
    private static function get_database(): RDM_Database {
        if (is_null(self::$database)) {
            self::$database = RDM_Database::instance();
        }
        return self::$database;
    }

    /**
     * Get table name with proper prefix
     *
     * @since 1.0.0
     * @param string $table_name Table name without prefix
     * @return string Full table name with prefix
     */
    public static function get_table_name(string $table_name): string {
        return self::get_database()->get_table_name($table_name);
    }

    /**
     * Execute a prepared query with error handling
     *
     * @since 1.0.0
     * @param string $query SQL query with placeholders
     * @param array $args Arguments for prepared statement
     * @param string $context Context for error logging
     * @return array|false Query results or false on failure
     */
    public static function execute_prepared_query(string $query, array $args = array(), string $context = ''): array|false {
        global $wpdb;

        try {
            $prepared_query = $wpdb->prepare($query, ...$args);
            $results = $wpdb->get_results($prepared_query, ARRAY_A);

            if ($wpdb->last_error) {
                error_log("RestroReach Database Error ({$context}): " . $wpdb->last_error);
                return false;
            }

            return $results;
        } catch (Exception $e) {
            error_log("RestroReach Database Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a single value query with error handling
     *
     * @since 1.0.0
     * @param string $query SQL query with placeholders
     * @param array $args Arguments for prepared statement
     * @param string $context Context for error logging
     * @return mixed Query result or false on failure
     */
    public static function execute_single_query(string $query, array $args = array(), string $context = '') {
        global $wpdb;

        try {
            $prepared_query = $wpdb->prepare($query, ...$args);
            $result = $wpdb->get_var($prepared_query);

            if ($wpdb->last_error) {
                error_log("RestroReach Database Error ({$context}): " . $wpdb->last_error);
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("RestroReach Database Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute an insert query with error handling
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param array $data Data to insert
     * @param array $format Format specifiers
     * @param string $context Context for error logging
     * @return int|false Insert ID or false on failure
     */
    public static function execute_insert(string $table, array $data, array $format = array(), string $context = ''): int|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $result = $wpdb->insert($table_name, $data, $format);

            if ($result === false) {
                error_log("RestroReach Database Insert Error ({$context}): " . $wpdb->last_error);
                return false;
            }

            return $wpdb->insert_id;
        } catch (Exception $e) {
            error_log("RestroReach Database Insert Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute an update query with error handling
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where Where conditions
     * @param array $format Format specifiers
     * @param array $where_format Where format specifiers
     * @param string $context Context for error logging
     * @return int|false Number of affected rows or false on failure
     */
    public static function execute_update(string $table, array $data, array $where, array $format = array(), array $where_format = array(), string $context = ''): int|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $result = $wpdb->update($table_name, $data, $where, $format, $where_format);

            if ($result === false) {
                error_log("RestroReach Database Update Error ({$context}): " . $wpdb->last_error);
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("RestroReach Database Update Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Execute a delete query with error handling
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param array $where Where conditions
     * @param array $where_format Where format specifiers
     * @param string $context Context for error logging
     * @return int|false Number of affected rows or false on failure
     */
    public static function execute_delete(string $table, array $where, array $where_format = array(), string $context = ''): int|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $result = $wpdb->delete($table_name, $where, $where_format);

            if ($result === false) {
                error_log("RestroReach Database Delete Error ({$context}): " . $wpdb->last_error);
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("RestroReach Database Delete Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a record exists
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param array $where Where conditions
     * @param array $where_format Where format specifiers
     * @param string $context Context for error logging
     * @return bool True if record exists, false otherwise
     */
    public static function record_exists(string $table, array $where, array $where_format = array(), string $context = ''): bool {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $where_clauses = array();
            $where_values = array();

            foreach ($where as $column => $value) {
                $where_clauses[] = "`{$column}` = %s";
                $where_values[] = $value;
            }

            $where_sql = implode(' AND ', $where_clauses);
            $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_sql}";

            $count = self::execute_single_query($query, $where_values, $context);
            return $count > 0;
        } catch (Exception $e) {
            error_log("RestroReach Database Exists Check Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a single record
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param array $where Where conditions
     * @param array $where_format Where format specifiers
     * @param string $context Context for error logging
     * @return array|false Record data or false on failure
     */
    public static function get_record(string $table, array $where, array $where_format = array(), string $context = ''): array|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $where_clauses = array();
            $where_values = array();

            foreach ($where as $column => $value) {
                $where_clauses[] = "`{$column}` = %s";
                $where_values[] = $value;
            }

            $where_sql = implode(' AND ', $where_clauses);
            $query = "SELECT * FROM {$table_name} WHERE {$where_sql} LIMIT 1";

            $results = self::execute_prepared_query($query, $where_values, $context);
            return $results ? $results[0] : false;
        } catch (Exception $e) {
            error_log("RestroReach Database Get Record Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get multiple records with optional pagination
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param array $where Where conditions (optional)
     * @param array $where_format Where format specifiers (optional)
     * @param string $order_by Order by clause (optional)
     * @param int $limit Limit number of records (optional)
     * @param int $offset Offset for pagination (optional)
     * @param string $context Context for error logging
     * @return array|false Records data or false on failure
     */
    public static function get_records(string $table, array $where = array(), array $where_format = array(), string $order_by = '', int $limit = 0, int $offset = 0, string $context = ''): array|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $query = "SELECT * FROM {$table_name}";
            $query_args = array();

            // Add WHERE clause if conditions provided
            if (!empty($where)) {
                $where_clauses = array();
                foreach ($where as $column => $value) {
                    $where_clauses[] = "`{$column}` = %s";
                    $query_args[] = $value;
                }
                $where_sql = implode(' AND ', $where_clauses);
                $query .= " WHERE {$where_sql}";
            }

            // Add ORDER BY clause
            if (!empty($order_by)) {
                $query .= " ORDER BY {$order_by}";
            }

            // Add LIMIT clause
            if ($limit > 0) {
                $query .= " LIMIT %d";
                $query_args[] = $limit;

                if ($offset > 0) {
                    $query .= " OFFSET %d";
                    $query_args[] = $offset;
                }
            }

            return self::execute_prepared_query($query, $query_args, $context);
        } catch (Exception $e) {
            error_log("RestroReach Database Get Records Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Count records in a table
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param array $where Where conditions (optional)
     * @param array $where_format Where format specifiers (optional)
     * @param string $context Context for error logging
     * @return int|false Count or false on failure
     */
    public static function count_records(string $table, array $where = array(), array $where_format = array(), string $context = ''): int|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $query = "SELECT COUNT(*) FROM {$table_name}";
            $query_args = array();

            // Add WHERE clause if conditions provided
            if (!empty($where)) {
                $where_clauses = array();
                foreach ($where as $column => $value) {
                    $where_clauses[] = "`{$column}` = %s";
                    $query_args[] = $value;
                }
                $where_sql = implode(' AND ', $where_clauses);
                $query .= " WHERE {$where_sql}";
            }

            $count = self::execute_single_query($query, $query_args, $context);
            return $count !== false ? (int) $count : false;
        } catch (Exception $e) {
            error_log("RestroReach Database Count Records Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Begin a database transaction
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function begin_transaction(): bool {
        global $wpdb;
        return $wpdb->query('START TRANSACTION') !== false;
    }

    /**
     * Commit a database transaction
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function commit_transaction(): bool {
        global $wpdb;
        return $wpdb->query('COMMIT') !== false;
    }

    /**
     * Rollback a database transaction
     *
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public static function rollback_transaction(): bool {
        global $wpdb;
        return $wpdb->query('ROLLBACK') !== false;
    }

    /**
     * Execute a transaction with automatic rollback on failure
     *
     * @since 1.0.0
     * @param callable $callback Function to execute within transaction
     * @param string $context Context for error logging
     * @return bool True on success, false on failure
     */
    public static function execute_transaction(callable $callback, string $context = ''): bool {
        try {
            if (!self::begin_transaction()) {
                error_log("RestroReach Database: Failed to begin transaction ({$context})");
                return false;
            }

            $result = $callback();

            if ($result === false) {
                self::rollback_transaction();
                error_log("RestroReach Database: Transaction callback failed, rolled back ({$context})");
                return false;
            }

            if (!self::commit_transaction()) {
                error_log("RestroReach Database: Failed to commit transaction ({$context})");
                return false;
            }

            return true;
        } catch (Exception $e) {
            self::rollback_transaction();
            error_log("RestroReach Database Transaction Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clean up old records from a table
     *
     * @since 1.0.0
     * @param string $table Table name
     * @param string $date_column Date column name
     * @param int $days_old Number of days old to consider for cleanup
     * @param string $context Context for error logging
     * @return int|false Number of deleted records or false on failure
     */
    public static function cleanup_old_records(string $table, string $date_column, int $days_old, string $context = ''): int|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $query = "DELETE FROM {$table_name} WHERE {$date_column} < DATE_SUB(NOW(), INTERVAL %d DAY)";
            
            $result = $wpdb->query($wpdb->prepare($query, $days_old));
            
            if ($result === false) {
                error_log("RestroReach Database Cleanup Error ({$context}): " . $wpdb->last_error);
                return false;
            }

            return $result;
        } catch (Exception $e) {
            error_log("RestroReach Database Cleanup Exception ({$context}): " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database table status
     *
     * @since 1.0.0
     * @param string $table Table name
     * @return array|false Table status or false on failure
     */
    public static function get_table_status(string $table): array|false {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $query = "SHOW TABLE STATUS LIKE %s";
            
            $result = self::execute_prepared_query($query, array($table_name), 'get_table_status');
            return $result ? $result[0] : false;
        } catch (Exception $e) {
            error_log("RestroReach Database Get Table Status Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if a table exists
     *
     * @since 1.0.0
     * @param string $table Table name
     * @return bool True if table exists, false otherwise
     */
    public static function table_exists(string $table): bool {
        global $wpdb;

        try {
            $table_name = self::get_table_name($table);
            $query = "SHOW TABLES LIKE %s";
            
            $result = self::execute_single_query($query, array($table_name), 'table_exists');
            return $result !== false;
        } catch (Exception $e) {
            error_log("RestroReach Database Table Exists Exception: " . $e->getMessage());
            return false;
        }
    }
} 