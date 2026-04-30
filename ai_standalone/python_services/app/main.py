import logging
from contextlib import asynccontextmanager
from typing import Annotated

from fastapi import FastAPI, HTTPException, Query

from .schemas import (
    BatchEmbeddingResponse,
    DeduplicateRequest,
    DeduplicateResponse,
    EmbeddingResponse,
    SimilarityRequest,
    SimilarityResponse,
    TextRequest,
    TextsRequest,
)
from .services import (
    cosine_similarity,
    deduplicate_texts,
    encode_text,
    encode_texts,
    load_model,
    model_loaded,
)
from .settings import settings

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(_: FastAPI):
    load_model()
    yield


app = FastAPI(title=settings.app_name, lifespan=lifespan)


@app.get("/")
def root():
    return {"message": settings.app_name, "model": settings.model_name}


@app.get("/live")
def live():
    return {"status": "alive"}


@app.get("/ready")
def ready():
    if not model_loaded():
        raise HTTPException(
            status_code=503,
            detail={"message": "Service unavailable", "model_loaded": False},
        )
    return {"status": "healthy", "model_loaded": True}


@app.get("/health")
def health():
    return ready()


@app.post("/embed", response_model=EmbeddingResponse)
def get_embedding(request: TextRequest):
    try:
        embedding = encode_text(request.text)
    except RuntimeError:
        raise HTTPException(status_code=503, detail="Model not loaded")
    except Exception:
        logger.exception("Error generating embedding")
        raise HTTPException(status_code=500, detail="Failed to generate embedding")

    return {"embedding": embedding.tolist(), "dimension": len(embedding)}


@app.post("/embed-batch", response_model=BatchEmbeddingResponse)
def get_batch_embeddings(request: TextsRequest):
    try:
        embeddings = encode_texts(request.texts)
    except RuntimeError:
        raise HTTPException(status_code=503, detail="Model not loaded")
    except Exception:
        logger.exception("Error generating batch embeddings")
        raise HTTPException(
            status_code=500, detail="Failed to generate batch embeddings"
        )

    return {
        "embeddings": embeddings.tolist(),
        "dimension": int(embeddings.shape[1]),
    }


@app.post("/similarity", response_model=SimilarityResponse)
def get_similarity(request: SimilarityRequest):
    if not request.text1.strip() or not request.text2.strip():
        return {
            "similarity": 0.0,
            "is_duplicate": False,
        }

    try:
        embeddings = encode_texts([request.text1, request.text2])
        similarity = cosine_similarity(embeddings[0], embeddings[1])
    except RuntimeError:
        raise HTTPException(status_code=503, detail="Model not loaded")
    except Exception:
        logger.exception("Error calculating similarity")
        raise HTTPException(status_code=500, detail="Failed to calculate similarity")

    return {
        "similarity": similarity,
        "is_duplicate": similarity > settings.duplicate_threshold,
    }


@app.post("/deduplicate", response_model=DeduplicateResponse)
def deduplicate(
    request: DeduplicateRequest,
    threshold: Annotated[float | None, Query(ge=0.0, le=1.0)] = None,
):
    try:
        current_threshold = request.threshold if threshold is None else threshold
        duplicates = deduplicate_texts(request.texts, current_threshold)
    except RuntimeError:
        raise HTTPException(status_code=503, detail="Model not loaded")
    except Exception:
        logger.exception("Error deduplicating texts")
        raise HTTPException(status_code=500, detail="Failed to deduplicate texts")

    return {"duplicates": duplicates, "count": len(duplicates)}
