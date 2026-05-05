from typing import List

from pydantic import BaseModel, Field, field_validator

from .settings import settings


class TextRequest(BaseModel):
    text: str = Field(..., min_length=1, max_length=settings.max_text_length)

    @field_validator("text")
    @classmethod
    def validate_text(cls, value: str) -> str:
        if not value.strip():
            raise ValueError("Text must not be blank")
        return value


class TextsRequest(BaseModel):
    texts: List[str] = Field(..., min_length=1, max_length=settings.max_batch_size)

    @field_validator("texts")
    @classmethod
    def validate_texts(cls, values: List[str]) -> List[str]:
        for value in values:
            if not value.strip():
                raise ValueError("Texts must not contain blank values")
            if len(value) > settings.max_text_length:
                raise ValueError(
                    f"Each text must be at most {settings.max_text_length} characters"
                )
        return values


class DeduplicateRequest(BaseModel):
    texts: List[str] = Field(
        ..., min_length=2, max_length=settings.max_deduplicate_batch_size
    )
    threshold: float = Field(
        default=settings.duplicate_threshold,
        ge=0.0,
        le=1.0,
    )

    @field_validator("texts")
    @classmethod
    def validate_texts(cls, values: List[str]) -> List[str]:
        for value in values:
            if not value.strip():
                raise ValueError("Texts must not contain blank values")
            if len(value) > settings.max_text_length:
                raise ValueError(
                    f"Each text must be at most {settings.max_text_length} characters"
                )
        return values


class SimilarityRequest(BaseModel):
    text1: str = Field(default="", max_length=settings.max_text_length)
    text2: str = Field(default="", max_length=settings.max_text_length)


class EmbeddingResponse(BaseModel):
    embedding: List[float]
    dimension: int


class BatchEmbeddingResponse(BaseModel):
    embeddings: List[List[float]]
    dimension: int


class SimilarityResponse(BaseModel):
    similarity: float
    is_duplicate: bool


class DuplicatePair(BaseModel):
    index1: int
    index2: int
    text1: str
    text2: str
    similarity: float


class DeduplicateResponse(BaseModel):
    duplicates: List[DuplicatePair]
    count: int
