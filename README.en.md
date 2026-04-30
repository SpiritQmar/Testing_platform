# AI Analytics - Exam Quality Analyzer

- **Question Validation** - check syllabus compliance
- **Quality Analysis** - analysis of question difficulty and discriminativity
- **Correlation Analysis** - correlation between questions and overall scores
- **Student Patterns** - analysis of student performance
- **Semantic Analysis** - semantic search for duplicates
- **Criteria Analysis** - analysis of evaluation criteria and performance
- **Student Answers** - analysis of student answers and plagiarism
- **Data Import** - loading syllabi and exam results

## Docker Setup

```bash
docker-compose up -d
```

- **app** - PHP application (http://localhost:8080)
- **db** - MySQL 8.0 (port 3307)
- **embeddings** - Python API for embeddings (port 8000)


## Local Installation

- **PHP:** 8.0 +
- **MySQL:** 5.7 +
- **Web server:** Apache (XAMPP)
- **Python:** 3.8+
- **PHP Extensions:** pdo_mysql, mbstring, json, zip, gd

### Configuration

1. Open http://localhost/phpmyadmin
2. Create a new database named `exam_analyzer_2`
3. Import SQL: `sql/full_database_setup.sql`
4. Configure database connection in `ai_standalone/config.php`
5. Default user will be created on first run:
   - Login: `superadmin`
   - Password: `superadmin123`

### Python Service

For embeddings, semantics and syllabus import uses Hugging Face model:
- Model: `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2`
- Support: Russian, English, Kazakh

```bash
cd ai_standalone/python_services
pip install -r requirements.txt
python -m uvicorn embeddings_api:app --host 127.0.0.1 --port 8000
```
