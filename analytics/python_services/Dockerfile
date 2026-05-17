FROM python:3.10-slim

WORKDIR /app

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    SENTENCE_TRANSFORMERS_HOME=/models/sentence_transformers

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app ./app
COPY embeddings_api.py .

EXPOSE 8000

RUN useradd --create-home --shell /bin/bash appuser \
    && mkdir -p /models/sentence_transformers \
    && chown -R appuser:appuser /app /models

ARG PRELOAD_MODEL=0
RUN if [ "$PRELOAD_MODEL" = "1" ]; then python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2')"; fi

USER appuser

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD python -c "import sys, urllib.request; sys.exit(0 if urllib.request.urlopen('http://127.0.0.1:8000/ready').status == 200 else 1)"

CMD ["uvicorn", "embeddings_api:app", "--host", "0.0.0.0", "--port", "8000"]
