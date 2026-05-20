# Stepik Clone (Vue + PHP + MySQL)

Учебный клон платформы с ролями:
- `user` (пользователь)
- `teacher` (преподаватель)
- `admin` (администратор)

## Стек
- Frontend: Vue 3 + Vite (JavaScript)
- Backend: PHP 8 (без фреймворка, REST API)
- Database: MySQL 8+

## Быстрый старт

### 1) База данных
1. Создай БД и таблицы:
   - Выполни SQL из `backend/schema.sql`.
2. При необходимости поменяй доступ к БД в `backend/config.php`.

### 2) Backend
Из папки `backend` запусти:

```bash
php -S localhost:8000 -t public
```

API будет доступно по `http://localhost:8000/api`.

### 3) Frontend
Из папки `frontend`:

```bash
npm install
npm run dev
```

Открой `http://localhost:5173`.

## Демо-аккаунты
В `schema.sql` уже добавлены:
- admin: `admin@example.com`
- teacher: `teacher@example.com`
- password для обоих: `password`

## Что реализовано
- Регистрация/логин
- Роли и разграничение доступа
- Публичный каталог курсов
- Создание курсов преподавателем
- Запись пользователя на курс
- Просмотр пользователей админом

## Следующий шаг для "один в один"
- Добавить CRUD модулей и уроков в UI преподавателя
- Тесты/вопросы/попытки
- Трекинг прогресса по урокам
- Комментарии и рейтинг
- Сертификаты
