<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

$conn = db();

function fetch_all_assoc(mysqli $conn, string $sql): array {
    $rows = [];
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

$salary = $conn->query("SELECT MIN(salary) AS min_salary, MAX(salary) AS max_salary FROM employees")->fetch_assoc();

json_response([
    'departments' => fetch_all_assoc($conn, "SELECT department_id AS id, name FROM departments ORDER BY name"),
    'towns' => fetch_all_assoc($conn, "SELECT town_id AS id, name FROM towns ORDER BY name"),
    'projects' => fetch_all_assoc($conn, "SELECT project_id AS id, name FROM projects ORDER BY name"),
    'salary' => [
        'min' => (float)$salary['min_salary'],
        'max' => (float)$salary['max_salary']
    ]
]);
