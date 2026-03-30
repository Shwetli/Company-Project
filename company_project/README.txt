# Company Project

## Какво е това
- PHP
- MySQL
- HTML/CSS/JS
- Excel export с SheetJS CDN

## Изисквания
- PHP 8+
- MySQL workbench
- XAMPP

## Стъпки за пускане

1. Създай база `company`

2. Импортирай файла `sql/company.sql`

3. Сложи папката в `htdocs` ако си с XAMPP

4. Провери връзката в `api/database.php`
   - host: `127.0.0.1`
   - user: `root`
   - pass: празна, ако си на XAMPP по default
   - db: `company`

5. Стартирай Apache и MySQL

6. Отвори:
   `http://localhost/simple_company_project/`

## Забележка
Ако delete не работи за някой employee, това е нарочно:
ако човекът е manager на department, проектът спира delete, за да не счупи foreign key връзките.
