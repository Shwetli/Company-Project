<?php
declare(strict_types=1);
require_once __DIR__ . '/database.php';

$conn = db();
$method = $_SERVER['REQUEST_METHOD'];

$sortMap = [
    'full_name' => "full_name",
    'job_title' => 'e.job_title',
    'department_name' => 'd.name',
    'salary' => 'e.salary',
    'address_text' => 'a.address_text',
    'town' => 't.name'
];

function esc_like(string $value): string {
    return '%' . $value . '%';
}

function get_filters(array $src): array {
    $where = [];
    $types = '';
    $params = [];

    if (!empty($src['name'])) {
        $where[] = "CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) LIKE ?";
        $types .= 's';
        $params[] = esc_like(trim((string)$src['name']));
    }
    if (!empty($src['job_title'])) {
        $where[] = 'e.job_title LIKE ?';
        $types .= 's';
        $params[] = esc_like(trim((string)$src['job_title']));
    }
    if (!empty($src['department_id'])) {
        $where[] = 'e.department_id = ?';
        $types .= 'i';
        $params[] = (int)$src['department_id'];
    }
    if (!empty($src['address'])) {
        $where[] = 'a.address_text LIKE ?';
        $types .= 's';
        $params[] = esc_like(trim((string)$src['address']));
    }
    if (!empty($src['town_id'])) {
        $where[] = 'a.town_id = ?';
        $types .= 'i';
        $params[] = (int)$src['town_id'];
    }
    if ($src['salary_min'] !== '' && isset($src['salary_min'])) {
        $where[] = 'e.salary >= ?';
        $types .= 'd';
        $params[] = (float)$src['salary_min'];
    }
    if ($src['salary_max'] !== '' && isset($src['salary_max'])) {
        $where[] = 'e.salary <= ?';
        $types .= 'd';
        $params[] = (float)$src['salary_max'];
    }

    return [$where, $types, $params];
}

function bind_dynamic(mysqli_stmt $stmt, string $types, array &$params): void {
    if ($types === '') return;
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    $stmt->bind_param($types, ...$refs);
}

function get_employee(mysqli $conn, int $id): array {
    $stmt = $conn->prepare("SELECT e.employee_id, e.first_name, e.middle_name, e.last_name, e.job_title, e.department_id, e.salary, a.address_text, a.town_id
                            FROM employees e
                            LEFT JOIN addresses a ON a.address_id = e.address_id
                            WHERE e.employee_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) json_response(['error' => 'Employee not found'], 404);

    $row['department_id'] = (int)$row['department_id'];
    $row['salary'] = (float)$row['salary'];
    $row['town_id'] = $row['town_id'] !== null ? (int)$row['town_id'] : null;

    $projects = [];
    $stmt2 = $conn->prepare('SELECT project_id FROM employees_projects WHERE employee_id = ?');
    $stmt2->bind_param('i', $id);
    $stmt2->execute();
    $res = $stmt2->get_result();
    while ($r = $res->fetch_assoc()) $projects[] = (int)$r['project_id'];
    $row['project_ids'] = $projects;
    return $row;
}

function set_projects(mysqli $conn, int $employeeId, array $projectIds): void {
    $stmt = $conn->prepare('DELETE FROM employees_projects WHERE employee_id = ?');
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();

    if (!$projectIds) return;
    $stmt = $conn->prepare('INSERT INTO employees_projects (employee_id, project_id) VALUES (?, ?)');
    foreach ($projectIds as $projectId) {
        $projectId = (int)$projectId;
        if ($projectId <= 0) continue;
        $stmt->bind_param('ii', $employeeId, $projectId);
        $stmt->execute();
    }
}

try {
    if ($method === 'GET') {
        if (!empty($_GET['id'])) json_response(get_employee($conn, (int)$_GET['id']));

        $sortBy = $_GET['sort_by'] ?? 'full_name';
        $sortDir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $sortExpr = $sortMap[$sortBy] ?? 'full_name';

        [$where, $types, $params] = get_filters($_GET);
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $baseFrom = " FROM employees e
            JOIN departments d ON d.department_id = e.department_id
            LEFT JOIN addresses a ON a.address_id = e.address_id
            LEFT JOIN towns t ON t.town_id = a.town_id
            LEFT JOIN employees_projects ep ON ep.employee_id = e.employee_id
            LEFT JOIN projects p ON p.project_id = ep.project_id ";

        $total = (int)$conn->query('SELECT COUNT(*) AS cnt FROM employees')->fetch_assoc()['cnt'];

        $countSql = 'SELECT COUNT(DISTINCT e.employee_id) AS cnt ' . $baseFrom . ' ' . $whereSql;
        $stmt = $conn->prepare($countSql);
        bind_dynamic($stmt, $types, $params);
        $stmt->execute();
        $filteredTotal = (int)$stmt->get_result()->fetch_assoc()['cnt'];

        $sql = "SELECT
                e.employee_id,
                CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS full_name,
                e.job_title,
                d.name AS department_name,
                e.salary,
                a.address_text,
                t.name AS town,
                COALESCE(GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', '), '') AS projects
                " . $baseFrom . ' ' . $whereSql . "
                GROUP BY e.employee_id, full_name, e.job_title, d.name, e.salary, a.address_text, t.name
                ORDER BY $sortExpr $sortDir";

        $exportAll = !empty($_GET['export_all']);
        $rows = [];

        if ($exportAll) {
            $stmt = $conn->prepare($sql);
            bind_dynamic($stmt, $types, $params);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $pageSize = max(1, min(100, (int)($_GET['page_size'] ?? 25)));
            $offset = ($page - 1) * $pageSize;
            $sql .= ' LIMIT ? OFFSET ?';
            $params2 = $params;
            $types2 = $types . 'ii';
            $params2[] = $pageSize;
            $params2[] = $offset;
            $stmt = $conn->prepare($sql);
            bind_dynamic($stmt, $types2, $params2);
            $stmt->execute();
            $res = $stmt->get_result();
        }

        while ($row = $res->fetch_assoc()) {
            $row['salary'] = (float)$row['salary'];
            $rows[] = $row;
        }

        json_response([
            'total' => $total,
            'filteredTotal' => $filteredTotal,
            'rows' => $rows
        ]);
    }

    $data = read_json();

    if ($method === 'POST' || $method === 'PUT') {
        $employeeId = (int)($data['employee_id'] ?? 0);
        $first = trim((string)($data['first_name'] ?? ''));
        $middle = trim((string)($data['middle_name'] ?? ''));
        $last = trim((string)($data['last_name'] ?? ''));
        $job = trim((string)($data['job_title'] ?? ''));
        $departmentId = (int)($data['department_id'] ?? 0);
        $salary = (float)($data['salary'] ?? 0);
        $addressText = trim((string)($data['address_text'] ?? ''));
        $townId = (int)($data['town_id'] ?? 0);
        $projectIds = is_array($data['project_ids'] ?? null) ? $data['project_ids'] : [];

        if ($first === '' || $last === '' || $job === '' || $departmentId <= 0 || $addressText === '' || $townId <= 0) {
            json_response(['error' => 'Missing required fields'], 400);
        }

        $conn->begin_transaction();

        $stmtAddress = $conn->prepare('INSERT INTO addresses (address_text, town_id) VALUES (?, ?)');
        $stmtAddress->bind_param('si', $addressText, $townId);
        $stmtAddress->execute();
        $addressId = (int)$conn->insert_id;

        $managerId = null;
        $stmtManager = $conn->prepare('SELECT manager_id FROM departments WHERE department_id = ?');
        $stmtManager->bind_param('i', $departmentId);
        $stmtManager->execute();
        $managerResult = $stmtManager->get_result()->fetch_assoc();
        if ($managerResult) $managerId = $managerResult['manager_id'];

        if ($method === 'POST') {
            $stmt = $conn->prepare('INSERT INTO employees (first_name, last_name, middle_name, job_title, department_id, manager_id, salary, address_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssiidi', $first, $last, $middle, $job, $departmentId, $managerId, $salary, $addressId);
            $stmt->execute();
            $employeeId = (int)$conn->insert_id;
        } else {
            $stmt = $conn->prepare('UPDATE employees SET first_name = ?, last_name = ?, middle_name = ?, job_title = ?, department_id = ?, manager_id = ?, salary = ?, address_id = ? WHERE employee_id = ?');
            $stmt->bind_param('ssssiidii', $first, $last, $middle, $job, $departmentId, $managerId, $salary, $addressId, $employeeId);
            $stmt->execute();
        }

        set_projects($conn, $employeeId, $projectIds);
        $conn->commit();
        json_response(['ok' => true, 'employee_id' => $employeeId]);
    }

    if ($method === 'DELETE') {
        $employeeId = (int)($data['employee_id'] ?? 0);
        if ($employeeId <= 0) json_response(['error' => 'Invalid employee id'], 400);

        $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM departments WHERE manager_id = ?');
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        if ($cnt > 0) {
            json_response(['error' => 'This employee is department manager. Delete blocked.'], 400);
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare('UPDATE employees SET manager_id = NULL WHERE manager_id = ?');
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();

        $stmt = $conn->prepare('DELETE FROM employees_projects WHERE employee_id = ?');
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();

        $stmt = $conn->prepare('DELETE FROM employees WHERE employee_id = ?');
        $stmt->bind_param('i', $employeeId);
        $stmt->execute();
        $conn->commit();

        json_response(['ok' => true]);
    }

    json_response(['error' => 'Method not allowed'], 405);

} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    json_response(['error' => $e->getMessage()], 500);
}
