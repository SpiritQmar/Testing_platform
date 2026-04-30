import os

from pydantic import BaseModel, ConfigDict, Field


def env_text(name: str, default: str) -> str:
    return os.getenv(name, default)


def env_int(name: str, default: int) -> int:
    value = os.getenv(name)
    return default if value is None else int(value)


def env_float(name: str, default: float) -> float:
    value = os.getenv(name)
    return default if value is None else float(value)


class Settings(BaseModel):
    model_config = ConfigDict(protected_namespaces=())

    app_name: str = env_text("EMBEDDINGS_APP_NAME", "AI Analytics Embeddings API")
    model_name: str = env_text(
        "EMBEDDINGS_MODEL_NAME",
        "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2",
    )
    duplicate_threshold: float = Field(
        default=env_float("EMBEDDINGS_DUPLICATE_THRESHOLD", 0.85),
        ge=0.0,
        le=1.0,
    )
    max_text_length: int = Field(
        default=env_int("EMBEDDINGS_MAX_TEXT_LENGTH", 5000),
        ge=1,
    )
    max_batch_size: int = Field(
        default=env_int("EMBEDDINGS_MAX_BATCH_SIZE", 128),
        ge=1,
    )
    max_deduplicate_batch_size: int = Field(
        default=env_int("EMBEDDINGS_MAX_DEDUPLICATE_BATCH_SIZE", 256),
        ge=2,
    )
    text_preview_length: int = Field(
        default=env_int("EMBEDDINGS_TEXT_PREVIEW_LENGTH", 100),
        ge=1,
    )


settings = Settings()
