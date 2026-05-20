# TeacherPro

Учебная платформа с системой ролей и модульной архитектурой.

## Содержание
- [Описание](#описание)
- [Технологический стек](#технологический-стек)
- [Структура проекта](#структура-проекта)
- [Установка и запуск](#установка-и-запуск)
- [Конфигурация](#конфигурация)
- [API](#api)
- [База данных](#база-данных)
- [Роли и права доступа](#роли-и-права-доступа)
- [Функциональность](#функциональность)
- [Разработка](#разработка)
- [Тестирование](#тестирование)
- [Деплой](#деплой)
- [Лицензия](#лицензия)

## Описание

TeacherPro — это платформа для создания и прохождения онлайн-курсов. Система поддерживает три типа пользователей: студенты, преподаватели и администраторы. Преподаватели могут создавать курсы, добавлять модули и уроки, формировать тесты. Студенты могут записываться на курсы, проходить обучение и получать сертификаты. Администраторы осуществляют модерацию контента и управление пользователями.

## Технологический стек

### Frontend
- Vue 3 (Composition API)
- Vite
- Vue Router
- JavaScript (ES6+)

### Backend
- PHP 8.0+
- Native REST API (без фреймворков)
- PDO для работы с базой данных
- JWT для аутентификации

### Database
- MySQL 8.0+
- InnoDB engine
- UTF8MB4 encoding

### Дополнительные инструменты
- npm / yarn для управления зависимостями
- php -S для локального сервера разработки

## Установка и запуск

### Требования
- PHP 8.0 или выше
- MySQL 8.0 или выше
- Node.js 16 или выше
- npm или yarn

### Шаг 1: Настройка базы данных

1. Создайте базу данных:
```sql
CREATE DATABASE teacherpro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Импортируйте схему:
```bash
mysql -u root -p teacherpro < backend/schema.sql
```

3. Настройте подключение в `backend/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'teacherpro');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### Шаг 2: Запуск backend

```bash
cd backend
php -S localhost:8085
```

API будет доступно по адресу: `http://localhost:8085/api`

### Шаг 3: Запуск frontend

```bash
cd frontend
npm install
npm run dev
```

Приложение откроется по адресу: `http://localhost:5173`

### Шаг 4: Проверка установки

1. Откройте `http://localhost:5173` в браузере
2. Войдите под тестовым аккаунтом (см. раздел [Демо-аккаунты](#демо-аккаунты))
3. Убедитесь, что загрузка курсов работает

## Конфигурация

### Переменные окружения

Создайте файл `.env` в папке `frontend/`:

```
VITE_API_URL=http://localhost:8085/api
VITE_APP_TITLE=TeacherPro
```

### Настройки backend

Файл `backend/config.php`:

```php
<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'teacherpro');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application settings
define('APP_NAME', 'TeacherPro');
define('APP_URL', 'http://localhost:8085');
define('JWT_SECRET', 'change_this_in_production');
define('JWT_EXPIRE', 86400); // 24 hours

// Error reporting
define('DEBUG_MODE', true);
```

## API

### Аутентификация

| Метод | Эндпоинт | Описание | Тело запроса |
|-------|----------|----------|--------------|
| POST | /auth/register | Регистрация пользователя | `{email, password, first_name, last_name}` |
| POST | /auth/login | Вход в систему | `{email, password}` |
| GET | /auth/me | Получение данных текущего пользователя | - |

### Курсы

| Метод | Эндпоинт | Описание | Доступ |
|-------|----------|----------|----------|
| GET | /courses | Список курсов | Все |
| GET | /courses/{id} | Данные курса | Все |
| POST | /courses | Создание курса | Teacher |
| PUT | /courses/{id} | Обновление курса | Owner/Admin |
| DELETE | /courses/{id} | Удаление курса | Owner/Admin |
| GET | /courses/{id}/structure | Структура курса | Enrolled |

### Уроки и тесты

| Метод | Эндпоинт | Описание | Доступ |
|-------|----------|----------|----------|
| GET | /lessons/{id} | Данные урока | Enrolled |
| POST | /lessons/{id}/complete | Завершение урока | Enrolled |
| GET | /lessons/{id}/quiz | Вопросы теста | Enrolled |
| POST | /lessons/{id}/quiz | Создание вопроса | Teacher |
| PUT | /quiz/{id} | Обновление вопроса | Teacher |

### Пользователи

| Метод | Эндпоинт | Описание | Доступ |
|-------|----------|----------|----------|
| GET | /profile | Данные профиля | Auth |
| PUT | /profile | Обновление профиля | Auth |
| GET | /user/enrollments | Записи пользователя | Auth |
| GET | /user/certificates | Сертификаты пользователя | Auth |

### Администрирование

| Метод | Эндпоинт | Описание | Доступ |
|-------|----------|----------|----------|
| GET | /admin/users | Список пользователей | Admin |
| PUT | /admin/users/{id} | Обновление пользователя | Admin |
| GET | /admin/courses | Все курсы | Admin |
| GET | /admin/courses/pending | Курсы на модерации | Admin |
| PUT | /admin/courses/{id}/status | Изменение статуса курса | Admin |

### Уведомления

| Метод | Эндпоинт | Описание | Доступ |
|-------|----------|----------|----------|
| GET | /notifications | Список уведомлений | Auth |
| POST | /notifications/{id}/read | Отметить как прочитанное | Auth |
| POST | /notifications/read-all | Отметить все как прочитанные | Auth |

### Формат запросов

Все запросы с телом должны отправляться с заголовком:
```
Content-Type: application/json
```

Для авторизованных запросов добавьте заголовок:
```
Authorization: Bearer {jwt_token}
```

### Формат ответов

Успешный ответ:
```json
{
  "message": "Operation successful",
  "data": {...}
}
```

Ошибка:
```json
{
  "error": "Error description",
  "debug": "Optional debug information"
}
```

## База данных

### Основные таблицы

#### users
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| email | VARCHAR(255) UNIQUE | Email пользователя |
| password | VARCHAR(255) | Хеш пароля |
| first_name | VARCHAR(100) | Имя |
| last_name | VARCHAR(100) | Фамилия |
| role | ENUM('user','teacher','admin') | Роль пользователя |
| bio | TEXT | Краткая биография |
| about | TEXT | Подробная информация |
| is_private | TINYINT(1) | Приватный профиль |
| created_at | TIMESTAMP | Дата регистрации |

#### courses
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| teacher_id | INT FOREIGN KEY | Автор курса |
| title | VARCHAR(255) | Название курса |
| description | TEXT | Краткое описание |
| what_you_learn | TEXT | Чему научится студент |
| about_course | TEXT | Подробное описание |
| for_whom | TEXT | Для кого курс |
| price | DECIMAL(10,2) | Цена курса |
| level | ENUM('beginner','intermediate','advanced') | Уровень сложности |
| status | ENUM('draft','pending','published') | Статус курса |
| certificate | VARCHAR(50) | Тип сертификата |
| created_at | TIMESTAMP | Дата создания |

#### course_modules
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| course_id | INT FOREIGN KEY | ID курса |
| title | VARCHAR(255) | Название модуля |
| sort_order | INT | Порядок отображения |

#### lessons
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| module_id | INT FOREIGN KEY | ID модуля |
| title | VARCHAR(255) | Название урока |
| content_type | ENUM('text','video') | Тип контента |
| lesson_type | ENUM('lesson','quiz') | Тип урока |
| content | TEXT | Текстовое содержание |
| video_url | VARCHAR(500) | Ссылка на видео |
| sort_order | INT | Порядок отображения |

#### quiz_answers
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| lesson_id | INT FOREIGN KEY | ID урока-теста |
| question | TEXT | Текст вопроса |
| options | JSON | Варианты ответов |
| correct_answer | JSON | Правильные ответы |
| sort_order | INT | Порядок отображения |

#### lesson_progress
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| lesson_id | INT FOREIGN KEY | ID урока |
| user_id | INT FOREIGN KEY | ID пользователя |
| completed_at | TIMESTAMP | Дата завершения |
| UNIQUE KEY (user_id, lesson_id) | | Уникальность записи |

#### certificates
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| user_id | INT FOREIGN KEY | ID пользователя |
| course_id | INT FOREIGN KEY | ID курса |
| certificate_code | VARCHAR(50) UNIQUE | Код сертификата |
| issued_at | TIMESTAMP | Дата выдачи |

#### notifications
| Поле | Тип | Описание |
|------|-----|----------|
| id | INT PRIMARY KEY | Уникальный идентификатор |
| user_id | INT FOREIGN KEY | ID получателя |
| type | VARCHAR(50) | Тип уведомления |
| title | VARCHAR(255) | Заголовок |
| message | TEXT | Текст уведомления |
| course_id | INT FOREIGN KEY | Связанный курс |
| course_name | VARCHAR(255) | Название курса |
| is_read | TINYINT(1) | Статус прочтения |
| created_at | TIMESTAMP | Дата создания |

## Роли и права доступа

### User (Студент)
- Просмотр публичного каталога курсов
- Запись на бесплатные курсы
- Прохождение уроков и тестов
- Просмотр прогресса обучения
- Получение сертификатов
- Управление профилем

### Teacher (Преподаватель)
- Все права пользователя
- Создание и редактирование курсов
- Добавление модулей и уроков
- Создание тестов и вопросов
- Просмотр статистики своих курсов
- Отправка курсов на модерацию

### Admin (Администратор)
- Все права преподавателя
- Модерация курсов (публикация/отклонение)
- Управление пользователями
- Изменение ролей пользователей
- Просмотр всей статистики платформы

## Функциональность

### Аутентификация и авторизация
- Регистрация новых пользователей
- Вход по email и паролю
- JWT-токены для сессий
- Защита маршрутов на основе ролей
- Обновление профиля и смена пароля

### Управление курсами
- Создание курсов с подробным описанием
- Добавление модулей и уроков
- Поддержка текстового и видео-контента
- Формирование тестов с множественным выбором
- Черновики и публикация через модерацию

### Обучение
- Интерфейс прохождения уроков
- Поддержка Markdown для форматирования текста
- Встраивание видео с YouTube и Vimeo
- Тесты с автоматической проверкой
- Отслеживание прогресса по урокам

### Сертификаты
- Автоматическая выдача при 100% завершении курса
- Уникальные коды сертификатов
- Просмотр полученных сертификатов в профиле

### Уведомления
- Центр уведомлений в интерфейсе
- Оповещения о результатах модерации
- Уведомления о получении сертификатов
- Маркировка прочитанных уведомлений

### Администрирование
- Панель управления пользователями
- Модерация курсов на публикацию
- Фильтрация и поиск по данным
- Статистика платформы

## Разработка

### Добавление нового модуля backend

1. Создайте папку в `backend/src/`:
```
backend/src/newmodule/
├── NewModuleController.php
└── NewModuleModel.php
```

2. Реализуйте модель:
```php
<?php
class NewModuleModel {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function findAll() {
        $stmt = $this->pdo->query("SELECT * FROM new_table");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
```

3. Реализуйте контроллер:
```php
<?php
require_once __DIR__ . '/NewModuleModel.php';

class NewModuleController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new NewModuleModel($pdo);
    }
    
    public function index() {
        $data = $this->model->findAll();
        jsonResponse(['data' => $data]);
    }
}
```

4. Добавьте маршрут в `router.php` или `index.php`

### Добавление нового компонента frontend

1. Создайте файл в соответствующей папке `components/`:
```vue
<!-- frontend/src/components/newmodule/NewComponent.vue -->
<script setup>
import { ref } from 'vue'

const props = defineProps({
  item: Object
})

const emit = defineEmits(['update'])
</script>

<template>
  <div class="new-component">
    <!-- контент -->
  </div>
</template>

<style scoped>
.new-component {
  /* стили */
}
</style>
```

2. Импортируйте и используйте в views или других компонентах

### Работа с API

Используйте готовый клиент в `api.js`:

```javascript
import { api } from './api'

// Получение данных
const courses = await api.getCourses()

// Отправка данных
await api.createCourse({
  title: 'Новый курс',
  description: 'Описание'
})

// Обработка ошибок
try {
  await api.protectedEndpoint()
} catch (err) {
  console.error(err.message)
}
```

## Тестирование

### Проверка вручную

1. Протестируйте основные сценарии:
   - Регистрация и вход
   - Создание курса преподавателем
   - Запись на курс студентом
   - Прохождение урока и теста
   - Получение сертификата
   - Модерация курса администратором

2. Проверьте права доступа:
   - Студент не может редактировать чужие курсы
   - Преподаватель не может публиковать курсы без модерации
   - Администратор имеет доступ ко всем функциям

### Логирование

Бэкенд ведёт лог ошибок в `backend/error.log`. При отладке проверяйте:
- Сообщения об исключениях
- SQL-запросы с ошибками
- Проблемы с аутентификацией

Для включения подробного логирования установите в `config.php`:
```php
define('DEBUG_MODE', true);
```

## Деплой

### Подготовка продакшена

1. Отключите режим отладки:
```php
// backend/config.php
define('DEBUG_MODE', false);
ini_set('display_errors', 0);
```

2. Соберите фронтенд:
```bash
cd frontend
npm run build
```

3. Настройте веб-сервер для раздачи `frontend/dist/` и проксирования `/api` на PHP

### Пример конфигурации Nginx

```nginx
server {
    listen 80;
    server_name your-domain.com;
    
    root /var/www/teacherpro/frontend/dist;
    index index.html;
    
    location / {
        try_files $uri $uri/ /index.html;
    }
    
    location /api {
        alias /var/www/teacherpro/backend;
        try_files $uri $uri/ /index.php?$query_string;
        
        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $request_filename;
        }
    }
}
```

### Безопасность

- Используйте HTTPS в продакшене
- Замените `JWT_SECRET` на надёжный ключ
- Ограничьте доступ к `backend/config.php`
- Настройте CORS при необходимости
- Регулярно обновляйте зависимости

## Лицензия

Учебный проект. Распространяется в образовательных целях.
