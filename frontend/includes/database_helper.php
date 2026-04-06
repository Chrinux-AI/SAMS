<?php
/**
 * Database Helper Functions
 * Provides safe database methods to replace db()->query() calls
 */

/**
 * Safe database execute function
 * Replaces problematic db()->query() calls
 */
function db_execute($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        return $stmt->execute($params);
    } catch (Exception $e) {
        error_log("Database execute error: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe database insert function
 * Returns last insert ID on success
 */
function db_insert($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return db()->lastInsertId();
    } catch (Exception $e) {
        error_log("Database insert error: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe database update function
 * Returns affected rows on success
 */
function db_update($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Database update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe database delete function
 * Returns affected rows on success
 */
function db_delete($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Database delete error: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe database fetch function
 * Returns single row or false
 */
function db_fetch($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Database fetch error: " . $e->getMessage());
        return false;
    }
}

/**
 * Safe database fetch all function
 * Returns all rows or empty array
 */
function db_fetch_all($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Database fetch all error: " . $e->getMessage());
        return [];
    }
}

/**
 * Safe database fetch column function
 * Returns single column value or false
 */
function db_fetch_column($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Database fetch column error: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if record exists
 */
function db_exists($query, $params = []) {
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Database exists check error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get database error info
 */
function db_error() {
    $error = db()->errorInfo();
    return $error[2] ?? 'Unknown database error';
}

/**
 * Begin database transaction
 */
function db_begin_transaction() {
    try {
        return db()->beginTransaction();
    } catch (Exception $e) {
        error_log("Database transaction begin error: " . $e->getMessage());
        return false;
    }
}

/**
 * Commit database transaction
 */
function db_commit() {
    try {
        return db()->commit();
    } catch (Exception $e) {
        error_log("Database transaction commit error: " . $e->getMessage());
        return false;
    }
}

/**
 * Rollback database transaction
 */
function db_rollback() {
    try {
        return db()->rollBack();
    } catch (Exception $e) {
        error_log("Database transaction rollback error: " . $e->getMessage());
        return false;
    }
}

?>
