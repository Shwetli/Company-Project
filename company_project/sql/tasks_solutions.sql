-- Решения по задачите от SQL_TASKS_SIMPLE.rtf

-- 1
SELECT CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name, job_title
FROM employees;

-- 3
SELECT CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name, salary
FROM employees
WHERE salary > 50000;

-- 4
SELECT name
FROM towns;

-- 5
SELECT a.address_text
FROM addresses a
JOIN towns t ON t.town_id = a.town_id
WHERE t.name = 'Sofia';

-- 6
SELECT CONCAT_WS(' ', e.first_name, e.middle_name, e.last_name) AS full_name
FROM employees e
JOIN departments d ON d.department_id = e.department_id
WHERE d.name = 'Sales';

-- 7
SELECT name, start_date
FROM projects
WHERE start_date > '2003-01-01';

-- 8
SELECT d.name, COUNT(e.employee_id) AS employees_count
FROM departments d
LEFT JOIN employees e ON e.department_id = d.department_id
GROUP BY d.department_id, d.name;

-- 9
SELECT CONCAT_WS(' ', first_name, middle_name, last_name) AS full_name
FROM employees
WHERE manager_id IS NULL;

-- 10
SELECT *
FROM projects
WHERE end_date IS NULL;
