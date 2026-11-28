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

// Builds "`column` IN ('a', 'b')" from a list of scalar values. Throws if the list is empty or
// has non-scalar values, so callers should only use it when there are values to match.
function build_in_clause($column, $values, callable $escape, $allowedColumns = null) {
    validate_column($column, $allowedColumns);
    if (!is_array($values) || count($values) === 0) {
        throw new InvalidArgumentException("$column must be a non-empty array");
    }
    $quoted = array();
    foreach ($values as $value) {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException("Invalid value for $column");
        }
        $quoted[] = "'" . $escape((string) $value) . "'";
    }
    return "`$column` IN (" . implode(', ', $quoted) . ")";
}

// Builds "LOWER(`column`) LIKE LOWER('%term%')" for a case-insensitive "contains" search. The
// LIKE wildcards (% and _) and the backslash escape character in the term are matched literally.
function build_contains_clause($column, $term, callable $escape, $allowedColumns = null) {
    validate_column($column, $allowedColumns);
    if (!is_scalar($term)) {
        throw new InvalidArgumentException("Invalid value for $column");
    }
    $literal = str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), (string) $term);
    return "LOWER(`$column`) LIKE LOWER('%" . $escape($literal) . "%')";
}

// Builds a clause that narrows designs.id to designs using the given colors, using the design_colors
// table. $colors is a list of "#rrggbb" values (other values are rejected). $match is 'any' (the design
// uses at least one of the colors) or 'all' (the design uses every color). When $exclusive is true,
// the design may not use any color outside the list. Returns '' when no colors are given.
function build_color_filter_clause($colors, $match, $exclusive, callable $escape) {
    if ($colors === null || $colors === array()) {
        return '';
    }
    if (!is_array($colors)) {
        throw new InvalidArgumentException('colors must be an array');
    }
    if ($match !== 'any' && $match !== 'all') {
        throw new InvalidArgumentException('colorsMatch must be any or all');
    }

    $selected = array();
    foreach ($colors as $color) {
        $normalized = is_string($color) ? normalize_hex_color($color) : null;
        if ($normalized === null) {
            throw new InvalidArgumentException('Invalid color');
        }
        $selected[$normalized] = true;
    }
    $selected = array_keys($selected);

    $quoted = array();
    foreach ($selected as $color) {
        $quoted[] = "'" . $escape($color) . "'";
    }
    $inList = implode(', ', $quoted);

    $having = '';
    if ($match === 'all') {
        $having = ' GROUP BY design_id HAVING COUNT(DISTINCT color) = ' . count($selected);
    }
    $clause = "designs.id IN (SELECT design_id FROM design_colors WHERE color IN ($inList)$having)";
    if ($exclusive) {
        $clause .= " AND designs.id NOT IN (SELECT design_id FROM design_colors WHERE color NOT IN ($inList))";
    }
    return $clause;
}

// Builds "a AND b" conditions (without a WHERE keyword) for an admin list filter, an array of
// column => term. Columns in $containsColumns use a case-insensitive "contains" match; every
// other column is an exact match. Returns '' when there is nothing to filter on.
function build_admin_filter_clause($filter, $containsColumns, callable $escape) {
    if ($filter === null) {
        return '';
    }
    if (!is_array($filter)) {
        throw new InvalidArgumentException('filter must be an array');
    }
    $conditions = array();
    foreach ($filter as $column => $value) {
        if (in_array($column, $containsColumns, true)) {
            $conditions[] = build_contains_clause($column, $value, $escape);
        } else {
            $conditions[] = build_where_conditions(array($column => $value), null, $escape);
        }
    }
    return implode(' AND ', $conditions);
}

// Builds the WHERE clause for the admin designs list. Besides the exact-match column filters, it
// supports case-insensitive "contains" searches on 'name', 'username' (the design's owner) and
// 'product_name' (the design's product). Returns '' when there is nothing to filter on.
function build_admin_design_filter_clause($filter, callable $escape) {
    if ($filter === null) {
        return '';
    }
    if (!is_array($filter)) {
        throw new InvalidArgumentException('filter must be an array');
    }
    $conditions = array();
    if (array_key_exists('username', $filter)) {
        $conditions[] = "`user` IN (SELECT `loginid` FROM `login` WHERE "
            . build_contains_clause('username', $filter['username'], $escape) . ")";
        unset($filter['username']);
    }
    if (array_key_exists('product_name', $filter)) {
        $conditions[] = "`product` IN (SELECT `id` FROM `products` WHERE "
            . build_contains_clause('name', $filter['product_name'], $escape) . ")";
        unset($filter['product_name']);
    }
    if (count($filter) > 0) {
        $conditions[] = build_admin_filter_clause($filter, array('name'), $escape);
    }
    if (count($conditions) === 0) {
        return '';
    }
    return 'WHERE ' . implode(' AND ', $conditions);
}

// Builds a clause that matches users by exact loginid or by a case-insensitive "contains" match on
// the username. Used by the admin user lookup.
function build_user_search_clause($term, callable $escape) {
    if (!is_scalar($term)) {
        throw new InvalidArgumentException('Invalid value for search');
    }
    return "(`loginid` = '" . $escape((string) $term) . "' OR "
        . build_contains_clause('username', $term, $escape) . ")";
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

// Builds "LIMIT n", "LIMIT n OFFSET m", or "LIMIT offset, count". The last form is MySQL's own
// syntax, which the public site uses for pagination (e.g. limit=24, 48).
function build_limit_clause($limit, $skip = null) {
    if ($limit === null) {
        if ($skip !== null) {
            throw new InvalidArgumentException('skip requires limit');
        }
        return '';
    }
    if (is_string($limit) && preg_match('/^(\d+)\s*,\s*(\d+)$/', $limit, $matches)) {
        if ($skip !== null) {
            throw new InvalidArgumentException('skip cannot be combined with an offset, count limit');
        }
        return 'LIMIT ' . (int) $matches[1] . ', ' . (int) $matches[2];
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
