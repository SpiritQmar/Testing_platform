# AI Analytics - Exam Quality Analyzer

**Language / Тіл / Language:**

[🇷🇺 Русский](README.ru.md) | [en English](README.en.md) | [🇰🇿 Қазақша](README.kz.md)


## Возможности

- **Валидация вопросов** - проверка соответствия силлабусу
- **Quality Analysis** - анализ сложности и дискриминативности вопросов
- **Correlation Analysis** - корреляция между вопросами и общими баллами
- **Student Patterns** - анализ успеваемости студентов
- **Semantic Analysis** - семантический поиск дубликатов
- **Criteria Analysis** - анализ критериев оценки и производительности
- **Student Answers** - анализ ответов студентов и плагиата
- **Импорт данных** - загрузка силлабусов и результатов экзаменов

## Запуск через Docker 
 
```bash
docker-compose up -d
```

- **app** - PHP приложение (http://localhost:8080)
- **db** - MySQL 8.0 (порт 3307)
- **embeddings** - Python API для эмбеддингов (порт 8000)
 

## Локальная установка

### Требования

- **PHP:** 8.0+
- **MySQL/MariaDB:** 5.7+
- **Web-сервер:** Apache/Nginx
- **Python:** 3.8+
- **Расширения PHP:** pdo_mysql, mbstring, json, zip, gd

### Настройки

1.  Открыть http://localhost/phpmyadmin
2. Создайте новую базу данных с именем `exam_analyzer_2` (можно поменять в конфигах)
3. Импортируйте SQL:  `sql/full_database_setup.sql`


### Python сервис

Для эмбеддингов, семантики и импорта силлабусов используется модель из Hugging Face:
- Модель: `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2`
- Поддержка: русский, английский, казахский

```bash
cd ai_standalone/python_services
pip install -r requirements.txt
python -m uvicorn embeddings_api:app --host 127.0.0.1 --port 8000
```