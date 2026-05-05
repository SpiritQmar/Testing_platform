import logging
from typing import Any, List

import numpy as np

from .settings import settings

logger = logging.getLogger(__name__)

loaded_model: Any | None = None


def load_model() -> None:
    global loaded_model
    if loaded_model is not None:
        return

    from sentence_transformers import SentenceTransformer

    logger.info("Loading model: %s", settings.model_name)
    loaded_model = SentenceTransformer(settings.model_name)
    logger.info("Model loaded successfully")


def get_model() -> Any | None:
    return loaded_model


def model_loaded() -> bool:
    return loaded_model is not None


def encode_text(text: str) -> np.ndarray:
    model = _require_model()
    return model.encode(text, convert_to_numpy=True)


def encode_texts(texts: List[str]) -> np.ndarray:
    model = _require_model()
    return model.encode(texts, convert_to_numpy=True)


def cosine_similarity(vector1: np.ndarray, vector2: np.ndarray) -> float:
    norm1 = np.linalg.norm(vector1)
    norm2 = np.linalg.norm(vector2)
    if norm1 == 0.0 or norm2 == 0.0:
        logger.warning("Zero-norm embedding encountered during similarity calculation")
        return 0.0

    similarity = float(np.dot(vector1, vector2) / (norm1 * norm2))
    return float(np.clip(similarity, -1.0, 1.0))


def deduplicate_texts(texts: List[str], threshold: float) -> List[dict]:
    embeddings = encode_texts(texts)
    normalized = _normalize_embeddings(embeddings)
    similarity_matrix = normalized @ normalized.T

    duplicates = []
    preview_length = settings.text_preview_length

    for i in range(len(texts)):
        for j in range(i + 1, len(texts)):
            similarity = float(np.clip(similarity_matrix[i, j], -1.0, 1.0))
            if similarity > threshold:
                duplicates.append(
                    {
                        "index1": i,
                        "index2": j,
                        "text1": _preview_text(texts[i], preview_length),
                        "text2": _preview_text(texts[j], preview_length),
                        "similarity": similarity,
                    }
                )

    return duplicates


def _normalize_embeddings(embeddings: np.ndarray) -> np.ndarray:
    norms = np.linalg.norm(embeddings, axis=1, keepdims=True)
    safe_norms = np.where(norms == 0.0, 1.0, norms)
    return embeddings / safe_norms


def _preview_text(text: str, max_length: int) -> str:
    if len(text) <= max_length:
        return text
    return f"{text[:max_length]}..."


def _require_model() -> Any:
    if loaded_model is None:
        raise RuntimeError("Model not loaded")
    return loaded_model
