<?php

#### Query building functions ####
# Helpers for turning request params into SQL fragments. Each throws an
# InvalidArgumentException on bad input, which handle_uncaught_exception turns into a 400.

// Column names must be plain identifiers. If $allowedColumns is null, any plain identifier is
// accepted (admin endpoints); otherwise the column must be in the list.
function validate_column($column, $allowedColumns = null) {
    if (!is_string($column) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
        throw new InvalidArgumentException('Invalid column name');
    }
    if ($allowedColumns !== null && !in_array($column, $allowedColumns, true)) {
        throw new InvalidArgumentException("Column $column is not allowed");
    }
    return $column;
}

// Builds "`a` = 'x' AND `b` = 'y'" from an array of column => value. $escape is called on every
// value, e.g. sql_escaper($conn).
function build_where_conditions($filter, $allowedColumns, callable $escape) {
    if (!is_array($filter)) {
        throw new InvalidArgumentException('filter must be an array');
    }
    $conditions = array();
    foreach ($filter as $column => $value) {
        validate_column($column, $allowedColumns);
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Invalid value for $column");
        }
        $conditions[] = "`$column` = '" . $escape((string) $value) . "'";
    }
    return implode(' AND ', $conditions);
}

// Builds "ORDER BY `column` ASC|DESC" from an array of [column, direction].
function build_order_clause($order, $allowedColumns = null) {
    if ($order === null) {
        return '';
    }
    if (!is_array($order) || !isset($order[0])) {
        throw new InvalidArgumentException('order must be [column, direction]');
    }
    $column = validate_column($order[0], $allowedColumns);
    $direction = isset($order[1]) && is_string($order[1]) ? strtoupper($order[1]) : 'ASC';
    if ($direction !== 'ASC' && $direction !== 'DESC') {
        throw new InvalidArgumentException('order direction must be ASC or DESC');
    }
    return "ORDER BY `$column` $direction";
}

// Builds "LIMIT n" or "LIMIT n OFFSET m".
function build_limit_clause($limit, $skip = null) {
    if ($limit === null) {
        if ($skip !== null) {
            throw new InvalidArgumentException('skip requires limit');
        }
        return '';
    }
    $clause = 'LIMIT ' . parse_non_negative_int($limit, 'limit');
    if ($skip !== null) {
        $clause .= ' OFFSET ' . parse_non_negative_int($skip, 'skip');
    }
    return $clause;
}

function parse_non_negative_int($value, $name) {
    if (is_int($value) && $value >= 0) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        return (int) $value;
    }
    throw new InvalidArgumentException("$name must be a non-negative integer");
}

// Validates the list of columns requested via return[].
function validate_return_columns($return, $allowedColumns = null) {
    if (!is_array($return)) {
        throw new InvalidArgumentException('return must be an array');
    }
    foreach ($return as $column) {
        validate_column($column, $allowedColumns);
    }
    return array_values($return);
}

function sql_escaper($conn) {
    return function ($value) use ($conn) {
        return mysqli_real_escape_string($conn, $value);
    };
}

function handle_uncaught_exception($exception) {
    $isBadRequest = $exception instanceof InvalidArgumentException;
    if (!$isBadRequest) {
        error_log((string) $exception);
    }
    // Never append an error body to a response that has already been sent.
    if (headers_sent()) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($isBadRequest ? 400 : 500);
    $message = $isBadRequest ? $exception->getMessage() : 'An unexpected error occurred';
    echo json_encode(array('message' => $message));
}

?>
