USE stepik_clone;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE lesson_progress;
TRUNCATE TABLE enrollments;
TRUNCATE TABLE lessons;
TRUNCATE TABLE course_modules;
TRUNCATE TABLE sessions;
TRUNCATE TABLE courses;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- password: password
SET @pwd := '$2y$10$7Zf0M1I6Bf2G1j5AOEJ2ReYw0Q7MYX6fGQX9zY8qjAojwM8gHTMEO';

INSERT INTO users (id, name, email, password_hash, role) VALUES
(1, 'Admin', 'admin@example.com', @pwd, 'admin'),
(2, 'Teacher Demo', 'teacher@example.com', @pwd, 'teacher'),
(3, 'Sergey Balakirev', 'sergey@example.com', @pwd, 'teacher'),
(4, 'Laysan Khutova', 'laysan@example.com', @pwd, 'teacher'),
(5, 'Nikita Filonov', 'nikita@example.com', @pwd, 'teacher'),
(6, 'Aleksey Kolesyachkin', 'aleksey@example.com', @pwd, 'teacher'),
(7, 'Ruslan Brantov', 'ruslan@example.com', @pwd, 'teacher'),
(8, 'Dmitriy Novikov', 'dnovikov@example.com', @pwd, 'teacher'),
(9, 'Ivan Vorobev', 'ivan@example.com', @pwd, 'teacher'),
(10, 'Student One', 'user1@example.com', @pwd, 'user'),
(11, 'Student Two', 'user2@example.com', @pwd, 'user'),
(12, 'Student Three', 'user3@example.com', @pwd, 'user');

INSERT INTO courses (title, description, teacher_id, status) VALUES
('Добрый, добрый C/C++ с Сергеем Балакиревым', 'Практический курс по C/C++ для новичков.', 3, 'published'),
('Графический дизайн с нуля', 'Портфолио и основы визуальной композиции.', 7, 'published'),
('ClickHouse: быстрый старт', 'Введение в OLAP и аналитические запросы.', 9, 'published'),
('LLM для Python-разработчиков: от RAG до агентов', 'Работа с LLM, промптами и агентами.', 5, 'published'),
('Автоматизация тестирования Backend с Python. Расширенный', 'Тестирование API и сервисов.', 5, 'published'),
('Лучший в IT. Как работает интернет + фронтенд', 'Основы интернета и фронтенда.', 2, 'published'),
('Инженер по тестированию: путь к веб-автоматизации', 'Тестирование UI и API на практике.', 6, 'published'),
('QA-интенсив: Тестирование микросервисной архитектуры', 'Продвинутый QA в микросервисах.', 5, 'published'),
('Введение в разработку ИИ-агентов', 'Базовые принципы AI-агентов.', 2, 'published'),
('Pytest: глубокое погружение', 'Практика автотестов на Python.', 6, 'published'),
('Логирование в Python: от новичка до эксперта', 'Логи, мониторинг и диагностика.', 3, 'published'),
('Поколение Python: профи + OOP', 'Объектно-ориентированное программирование.', 2, 'published'),
('Пакет курсов Data Scientist: Python + SQL + ML', 'Полный путь в Data Science.', 9, 'published'),
('Профессия: Python-разработчик', 'Путь к первой работе Python-разработчиком.', 2, 'published'),
('Программа курсов: Бэкенд-разработка на Python', 'Серия курсов по backend-стеку Python.', 5, 'published'),
('Информатика ЕГЭ 2026 — все в одном', 'Подготовка к ЕГЭ по информатике.', 4, 'published'),
('Соточка по русскому: помощник к ЕГЭ 2026', 'Подготовка к ЕГЭ по русскому языку.', 4, 'published'),
('ЕГЭ 2026 английский. Грамматика под ключ', 'Разбор грамматики и задач ЕГЭ.', 4, 'published'),
('Нейросети с нуля до результата', 'Практика нейросетей в реальных задачах.', 8, 'published'),
('ChatGPT как диплом', 'Как использовать AI в учебе и работе.', 8, 'published'),
('Нейросети в SMM и маркетинге', 'AI-инструменты для маркетинга.', 8, 'published'),
('SQL от нуля до уверенного', 'Реляционные базы данных и SQL.', 9, 'published'),
('Docker + Kubernetes для разработчика', 'Контейнеризация и оркестрация.', 8, 'published'),
('Анализ данных в Python', 'Pandas, NumPy, визуализация.', 9, 'published'),
('Основы JavaScript', 'Базовый курс по JavaScript для новичков.', 2, 'published');

-- По одному модулю на каждый курс
INSERT INTO course_modules (course_id, title, sort_order)
SELECT id, CONCAT('Модуль 1: Введение в курс "', title, '"'), 1
FROM courses;

-- По одному уроку на модуль
INSERT INTO lessons (module_id, title, content_type, content, sort_order)
SELECT
  m.id,
  CONCAT('Урок 1: Старт'),
  'text',
  CONCAT('Добро пожаловать в курс "', c.title, '". В этом уроке вы познакомитесь со структурой и целями курса.'),
  1
FROM course_modules m
JOIN courses c ON c.id = m.course_id;

-- Записи пользователей на курсы
INSERT INTO enrollments (user_id, course_id) VALUES
(10, 1), (10, 4), (10, 11), (10, 16), (10, 19),
(11, 2), (11, 7), (11, 12), (11, 17), (11, 22),
(12, 3), (12, 5), (12, 8), (12, 18), (12, 24);

-- Прогресс по урокам (для первых 3 пользователей)
INSERT INTO lesson_progress (user_id, lesson_id)
SELECT 10, l.id FROM lessons l JOIN course_modules m ON m.id = l.module_id WHERE m.course_id IN (1, 4, 11);
INSERT INTO lesson_progress (user_id, lesson_id)
SELECT 11, l.id FROM lessons l JOIN course_modules m ON m.id = l.module_id WHERE m.course_id IN (2, 7, 12);
INSERT INTO lesson_progress (user_id, lesson_id)
SELECT 12, l.id FROM lessons l JOIN course_modules m ON m.id = l.module_id WHERE m.course_id IN (3, 8, 24);
