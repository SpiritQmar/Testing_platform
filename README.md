# AI Analytics - Exam Quality Analyzer

- **Валидация вопросов** - проверка соответствия силлабусу
- **Quality Analysis** - анализ сложности и дискриминативности вопросов
- **Correlation Analysis** - корреляция между вопросами и общими баллами
- **Student Patterns** - анализ успеваемости студентов
- **Semantic Analysis** - семантический поиск дубликатов
- **Импорт данных** - загрузка силлабусов и результатов экзаменов

## Запуск через Docker 
 
 
```bash
docker-compose up -d
```

- **app** - PHP приложение (http://localhost:8080)
- **db** - MySQL 8.0 (порт 3307)
- **embeddings** - Python API для эмбеддингов (порт 8000)
 

## Локальная установка

- **PHP:** 8.0 +
- **MySQL/MariaDB:** 5.7 +
- **Web-сервер:** пример Apache (XAMPP)
- **Python:** 3.8+
- **Расширения PHP:**
  - pdo_mysql
  - mbstring
  - json
  - zip
  - gd

### Настройки

1.  Открыть http://localhost/phpmyadmin
2. Создайте новую базу данных
3. Импортируйте SQL:  `ai_analytics.sql` , `exam_analyzer_2_full.sql`
4. При первом запуске будет создан пользователь по умолчанию:
   - Логин: `superadmin`
   - Пароль: `superadmin123`

### Python сервис

Для эмбеддингов, семантики и импорта силлабусов:
```bash
cd ai_standalone/python_services
pip install -r requirements.txt
python embeddings_api.py
```


 

