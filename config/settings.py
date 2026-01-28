#настройки проекта
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
DATA_DIR = BASE_DIR / "data"
RAW_DATA_DIR = DATA_DIR / "raw"
PROCESSED_DATA_DIR = DATA_DIR / "processed"

DATABASE_URL = os.getenv("DATABASE_URL", "sqlite:///./test.db")
MODEL_PATH = BASE_DIR / "models/sentence_transformers"
LOG_LEVEL = "INFO"