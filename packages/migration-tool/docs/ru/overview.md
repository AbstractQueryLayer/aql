# Система Миграций AQL MigrationTool

## Обзор

MigrationTool — это система управления миграциями базы данных для AQL, 
обеспечивающая версионный контроль схемы и данных БД. Система поддерживает как SQL, 
так и PHP миграции, обеспечивает атомарность операций и возможность отката изменений.

## Основные Концепции

### Migration (Миграция)

**Миграция** — это логическая группа операций, связанных с одной задачей (task). 
Миграция идентифицируется по имени задачи (например, `TASK-123`).

Одна миграция может содержать несколько операций:
- Создание таблицы
- Добавление индексов
- Миграция данных
- Обновление связей

### MigrationOperation (Операция Миграции)

**Операция миграции** — это элементарная единица изменения базы данных, представленная одним файлом.

Каждая операция содержит:
- **Version** — глобальный номер версии (порядковый номер)
- **TaskName** — имя задачи для группировки (например, `TASK-123`)
- **Description** — описание операции
- **Code** — код для выполнения (SQL или PHP)
- **RollbackCode** — код для отката
- **Checksum** — контрольная сумма для проверки целостности

### Жизненный Цикл Операции

Операция миграции проходит следующие состояния:

1. **pending** — ожидает выполнения
2. **running** — выполняется в данный момент
3. **completed** — успешно выполнена
4. **failed** — выполнение завершилось с ошибкой
5. **rollback** — операция была отменена

---

## Структура Файлов

### Организация Папок

Миграции организованы по папкам с датами в формате `YYYY-MM-DD`:

```
migrations/
├── 2025-01-10/
│   ├── 0001-TASK-123-create-users-table_up.sql
│   ├── 0001-TASK-123-create-users-table_down.sql
│   ├── 0002-TASK-123-add-users-indexes_up.sql
│   └── 0002-TASK-123-add-users-indexes_down.sql
├── 2025-01-11/
│   ├── 0003-TASK-456-migrate-user-data.php
│   └── 0004-TASK-789-add-roles-table_up.sql
└── 2025-01-12/
    └── 0005-TASK-789-add-roles-indexes_up.sql
```

**Преимущества структуры по датам:**
- Быстрое сканирование только новых миграций
- Логическая группировка по времени разработки
- Упрощение навигации в больших проектах

### Формат Имён Файлов

#### SQL Миграции (два файла)

**Формат:** `{version}-{taskName}-{description}_up.sql` + `{version}-{taskName}-{description}_down.sql`

**Примеры:**
```
0001-TASK-123-create-users-table_up.sql
0001-TASK-123-create-users-table_down.sql
```

**Компоненты:**
- `0001` — глобальный номер версии (порядковый номер)
- `TASK-123` — имя задачи (идентификатор миграции)
- `create-users-table` — описание операции
- `_up` / `_down` — направление (накат/откат)
- `.sql` — тип файла

#### PHP Миграции (один файл)

**Формат:** `{version}-{taskName}-{description}.php`

**Пример:**
```
0003-TASK-456-migrate-user-data.php
```

PHP файл содержит оба метода (`up()` и `down()`) в одном классе.

---

## Как Работает Система

### 1. Определение Порядка Выполнения

Система определяет порядок выполнения миграций на основе:

1. **Номера версии (version)** — глобальный порядковый номер
   - Миграции выполняются в порядке возрастания версии
   - Версия уникальна для каждой операции

2. **Даты папки (migrationDate)** — дата создания миграции
   - Используется для оптимизации сканирования
   - Система сканирует только папки с датой >= даты последней выполненной миграции

3. **Имени задачи (taskName)** — группировка операций
   - Операции с одинаковым taskName принадлежат одной миграции
   - Выполняются последовательно в рамках одной миграции

### 2. Tracking Таблица (База Данных)

Система хранит информацию о выполненных операциях в таблице `migration`:

| Колонка        | Тип           | Описание                |
|----------------|---------------|-------------------------|
| id             | BIGINT        | Первичный ключ          |
| version        | INT           | Номер версии операции   |
| task_name      | VARCHAR(255)  | Имя задачи              |
| description    | VARCHAR(500)  | Описание операции       |
| migration_date | VARCHAR(10)   | Дата папки (YYYY-MM-DD) |
| type           | VARCHAR(10)   | Тип (sql/php)           |
| file_path      | VARCHAR(1000) | Путь к файлу            |
| code           | TEXT          | Код операции (накат)    |
| rollback_code  | TEXT          | Код отката              |
| checksum       | VARCHAR(64)   | SHA-256 хеш кода        |
| status         | VARCHAR(20)   | Статус операции         |
| started_at     | DATETIME      | Время начала            |
| completed_at   | DATETIME      | Время завершения        |

### 3. Алгоритм Выполнения Миграций

```
1. Получить последнюю выполненную операцию из БД
   └─> Извлечь migrationDate последней операции

2. Сканировать папки миграций
   └─> Начиная с папки >= migrationDate
   └─> Парсить имена файлов
   └─> Читать содержимое файлов

3. Группировать операции по taskName
   └─> Создать объекты Migration

4. Фильтровать pending операции
   └─> Проверить: есть ли (version + taskName) в БД?
   └─> Если НЕТ → PENDING (к выполнению)
   └─> Если ДА → SKIP (уже выполнена)

5. Выполнить pending операции
   └─> Для каждой операции:
       ├─> Установить status = "running"
       ├─> Записать started_at
       ├─> Выполнить код (SQL или PHP)
       ├─> Установить status = "completed"
       ├─> Записать completed_at
       └─> Сохранить в БД (code + rollback_code + checksum)

6. При ошибке:
   └─> Установить status = "failed"
   └─> Остановить выполнение
   └─> Не сохранять операцию в БД (ROLLBACK транзакции)
```

### 4. Checksum Валидация

При каждом запуске система проверяет целостность:

1. Читает содержимое файлов миграций
2. Вычисляет SHA-256 хеш (`code + rollbackCode`)
3. Сравнивает с checksum в БД
4. Если не совпадает → **ERROR** (файл изменён после выполнения)

**Защита:**
- Примененные миграции нельзя изменять
- Гарантия согласованности кода в БД и файлах
- Предотвращение случайных модификаций

---

## Работа с Миграциями

### Выполнение Миграций

```php
use IfCastle\AQL\MigrationTool\Manager\MigrationManagerInterface;

// Выполнить все pending миграции
$executedMigrations = $migrationManager->migrate();

foreach ($executedMigrations as $migration) {
    echo "Executed: {$migration->getMigrationName()}\n";
}
```

**Что происходит:**
1. Система сканирует файлы миграций
2. Определяет pending операции
3. Группирует операции по taskName
4. Выполняет операции в порядке version
5. Сохраняет результаты в БД

### Откат Миграций

```php
// Откатить последнюю операцию
$migrationManager->rollback(1);

// Откатить последние 3 операции
$migrationManager->rollback(3);
```

**Что происходит:**
1. Система читает выполненные операции из БД
2. Берёт последние N операций
3. Выполняет rollback_code в обратном порядке
4. Обновляет статус на "rollback"

### Проверка Статуса

```php
$status = $migrationManager->getStatus();

echo "Executed migrations: " . count($status['executed']) . "\n";
echo "Pending migrations: " . count($status['pending']) . "\n";

foreach ($status['pending'] as $migration) {
    echo "Pending: {$migration->getMigrationName()}\n";

    foreach ($migration->getMigrationOperations() as $operation) {
        echo "  - v{$operation->getVersion()}: {$operation->getDescription()}\n";
    }
}
```

### Получение Pending Миграций

```php
$pending = $migrationManager->getPendingMigrations();

foreach ($pending as $migration) {
    echo "Migration: {$migration->getMigrationName()}\n";
    echo "Operations count: " . count($migration->getMigrationOperations()) . "\n";
}
```

---

## Типы Миграций

### SQL Миграции

SQL миграции состоят из двух файлов: `_up.sql` (накат) и `_down.sql` (откат).

**Файл: 0001-TASK-123-create-users-table_up.sql**
```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY unique_email (email)
);
```

**Файл: 0001-TASK-123-create-users-table_down.sql**
```sql
DROP TABLE IF EXISTS users;
```

**Особенности:**
- Простые DDL операции
- Декларативное описание изменений
- Быстрое выполнение
- Легко читается и проверяется

### PHP Миграции

PHP миграции содержат оба метода в одном файле и позволяют выполнять сложную логику.

**Файл: 0003-TASK-456-migrate-user-data.php**
```php
<?php
declare(strict_types=1);

namespace Migrations;

use IfCastle\AQL\MigrationTool\MigrationOperationInterface;

class Migration0003_TASK456_MigrateUserData implements MigrationOperationInterface
{
    public function up(): void
    {
        // Сложная логика миграции данных
        $users = $this->db->query("SELECT * FROM old_users");

        foreach ($users as $user) {
            $this->db->insert('users', [
                'username' => $user['name'],
                'email' => $user['mail'],
                'created_at' => new DateTime($user['registration_date'])
            ]);
        }
    }

    public function down(): void
    {
        // Откат: очистка мигрированных данных
        $this->db->query("DELETE FROM users WHERE created_at >= ?", [$this->migrationStartTime]);
    }
}
```

**Преимущества:**
- Условная логика
- Пакетная обработка данных
- Валидация данных при миграции
- Использование ORM/Query Builder
- Обработка ошибок
- Логирование прогресса

---

## Группировка Операций по TaskName

Несколько операций могут быть объединены в одну миграцию через общий **taskName**.

**Пример:**

```
2025-01-10/
├── 0001-TASK-123-create-users-table_up.sql
├── 0001-TASK-123-create-users-table_down.sql
├── 0002-TASK-123-add-users-indexes_up.sql
├── 0002-TASK-123-add-users-indexes_down.sql
├── 0003-TASK-123-create-roles-table_up.sql
└── 0003-TASK-123-create-roles-table_down.sql
```

Все эти операции будут сгруппированы в одну миграцию `TASK-123`:

```php
$migration = $migrationManager->getPendingMigrations()[0];

echo $migration->getMigrationName(); // "TASK-123"
echo count($migration->getMigrationOperations()); // 3

// Операции выполняются последовательно:
// 1. v0001: create-users-table
// 2. v0002: add-users-indexes
// 3. v0003: create-roles-table
```

**Логика группировки:**
- TaskName — это логический идентификатор миграции
- Все операции с одинаковым taskName принадлежат одной миграции
- Операции выполняются в порядке возрастания version
- При откате все операции миграции откатываются вместе

---

## Оптимизация Производительности

### Сканирование Только Новых Папок

Система оптимизирует сканирование файловой системы:

1. Читает последнюю выполненную операцию из БД
2. Извлекает `migrationDate` (например, `2025-01-10`)
3. Сканирует только папки >= `2025-01-10`
4. Пропускает старые папки с уже выполненными миграциями

**Пример:**

```
Последняя миграция: 2025-01-10
Папки:
  2025-01-08/  ← SKIP (старая)
  2025-01-09/  ← SKIP (старая)
  2025-01-10/  ← SCAN (может быть новые операции)
  2025-01-11/  ← SCAN (новая)
  2025-01-12/  ← SCAN (новая)
```

**Преимущества:**
- Константное время сканирования (не зависит от общего числа миграций)
- Быстрый запуск на проектах с тысячами миграций
- Минимальная нагрузка на файловую систему

### Кеширование Метаданных

Система хранит метаданные операций в БД:
- Не нужно парсить уже выполненные файлы
- Checksum проверяется только при необходимости
- История миграций доступна без чтения файлов

---

## Безопасность и Контроль

### Immutability (Неизменяемость)

**Правило:** Примененные миграции нельзя изменять.

**Механизм:**
- Checksum сохраняется в БД при выполнении
- При каждом запуске checksum пересчитывается
- Если checksum не совпадает → ERROR

**Защита:**
- Предотвращение случайных изменений истории
- Гарантия воспроизводимости
- Аудит изменений схемы

### Транзакционность

**Каждая операция выполняется в транзакции:**

```
BEGIN TRANSACTION
  ├─> Выполнить код миграции
  ├─> Сохранить метаданные в tracking таблицу
  └─> COMMIT (только при успехе)

При ошибке:
  └─> ROLLBACK (откат изменений + не сохранять в БД)
```

**Гарантии:**
- Атомарность: либо вся операция, либо ничего
- Согласованность: БД всегда в валидном состоянии
- Откат при ошибках: автоматический ROLLBACK

### Gap Detection

Система проверяет пропуски в версиях:

```
Найденные версии: 1, 2, 3, 5, 6
                           ↑
                    Пропуск v4 → ERROR
```

**Защита:**
- Предотвращение случайных пропусков
- Гарантия последовательности
- Контроль целостности истории

---

## Best Practices

### Именование Файлов

✅ **Хорошо:**
```
0001-TASK-123-create-users-table_up.sql
0002-TASK-123-add-email-index_up.sql
0003-FEATURE-456-add-roles-system.php
```

❌ **Плохо:**
```
migration1.sql                    # Нет taskName
create_users.sql                  # Нет version
001-create-users_up.sql          # Нет taskName
v1-users-table_up.sql            # Неправильный формат version
```

### Структура Операций

✅ **Одна операция = одно логическое изменение:**
```
0001-TASK-123-create-users-table_up.sql     # Только CREATE TABLE
0002-TASK-123-add-users-indexes_up.sql      # Только индексы
0003-TASK-123-add-foreign-keys_up.sql       # Только FK
```

❌ **Не смешивать разные изменения:**
```sql
-- Плохо: всё в одном файле
CREATE TABLE users (...);
CREATE INDEX idx_email ON users(email);
ALTER TABLE orders ADD CONSTRAINT fk_user ...;
INSERT INTO settings ...;
```

### Backward Compatibility

✅ **Делать миграции обратно совместимыми:**
```sql
-- Сначала добавить nullable колонку
ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL;

-- Позже (в другой миграции) заполнить данные
UPDATE users SET phone = '000-000-0000' WHERE phone IS NULL;

-- Еще позже (в третьей миграции) сделать NOT NULL
ALTER TABLE users MODIFY COLUMN phone VARCHAR(20) NOT NULL;
```

### Группировка по Задачам

✅ **Все операции одной фичи в одном taskName:**
```
0010-FEATURE-789-user-authentication_up.sql      # Таблица users
0011-FEATURE-789-user-authentication_up.sql      # Таблица sessions
0012-FEATURE-789-user-authentication.php         # Миграция данных
```

**Преимущества:**
- Логическая связность операций
- Простота отката всей фичи
- Понятная история изменений

---

## Примеры Использования

### Создание Новой Миграции

1. Определить номер version (следующий после последнего)
2. Определить taskName (например, `TASK-123` или `FEATURE-456`)
3. Создать папку с текущей датой (если не существует)
4. Создать файлы миграции

**Пример: Добавление таблицы products**

```bash
# Создать папку
mkdir migrations/2025-01-12

# Создать SQL миграцию
cat > migrations/2025-01-12/0015-TASK-789-create-products-table_up.sql << EOF
CREATE TABLE products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at DATETIME NOT NULL
);
EOF

cat > migrations/2025-01-12/0015-TASK-789-create-products-table_down.sql << EOF
DROP TABLE IF EXISTS products;
EOF
```

### Миграция с Условной Логикой

**Пример: Миграция данных с валидацией**

```php
<?php
// migrations/2025-01-12/0016-TASK-789-migrate-old-products.php

namespace Migrations;

class Migration0016_TASK789_MigrateOldProducts
{
    public function up(): void
    {
        $oldProducts = $this->db->query("SELECT * FROM legacy_products");
        $migrated = 0;
        $skipped = 0;

        foreach ($oldProducts as $product) {
            // Валидация
            if (empty($product['name']) || $product['price'] <= 0) {
                $this->log("Skipped invalid product: {$product['id']}");
                $skipped++;
                continue;
            }

            // Трансформация данных
            $this->db->insert('products', [
                'name' => trim($product['name']),
                'price' => round($product['price'], 2),
                'created_at' => new DateTime($product['created_date'] ?? 'now')
            ]);

            $migrated++;
        }

        $this->log("Migrated: {$migrated}, Skipped: {$skipped}");
    }

    public function down(): void
    {
        // Откат: удалить мигрированные данные
        $this->db->query("TRUNCATE TABLE products");
    }
}
```

---

## Troubleshooting

### Checksum Mismatch Error

**Проблема:**
```
MigrationException: Checksum mismatch for migration v0005-TASK-123
Expected: abc123...
Got: def456...
```

**Причина:** Файл миграции был изменён после выполнения.

**Решение:**
1. Откатить миграцию: `$manager->rollback(1)`
2. Применить снова с новым кодом
3. Или: создать новую миграцию с исправлениями

### Gap in Versions

**Проблема:**
```
MigrationException: Gap detected in migration versions
Found: 1, 2, 3, 5
Missing: 4
```

**Причина:** Отсутствует файл миграции с версией 4.

**Решение:**
1. Найти пропущенную миграцию
2. Добавить файл в правильную папку
3. Или: пересоздать миграции с правильными версиями

### Migration Failed

**Проблема:**
```
MigrationException: Migration failed at v0008-TASK-456
Status: failed
```

**Причина:** Ошибка выполнения SQL/PHP кода.

**Решение:**
1. Проверить логи ошибок
2. Исправить код миграции
3. Откатить failed миграцию
4. Применить исправленную версию

---

## Заключение

MigrationTool обеспечивает надёжное управление эволюцией базы данных с:

- ✅ Версионным контролем схемы
- ✅ Атомарными операциями
- ✅ Возможностью отката
- ✅ Группировкой по задачам
- ✅ Оптимизацией производительности
- ✅ Контролем целостности
- ✅ Поддержкой SQL и PHP миграций

Следуйте best practices, и ваша база данных всегда будет в согласованном и контролируемом состоянии.
