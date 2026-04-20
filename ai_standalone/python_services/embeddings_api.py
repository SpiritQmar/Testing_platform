from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List, Optional
import numpy as np
from sentence_transformers import SentenceTransformer
import logging

logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

app = FastAPI()

MODEL_NAME = "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"
model = None

class TextRequest(BaseModel):
    text: str

class TextsRequest(BaseModel):
    texts: List[str]

class SimilarityRequest(BaseModel):
    text1: str
    text2: str

class EmbeddingResponse(BaseModel):
    embedding: List[float]
    dimension: int

class SimilarityResponse(BaseModel):
    similarity: float
    is_duplicate: bool

class BatchEmbeddingResponse(BaseModel):
    embeddings: List[List[float]]
    dimension: int

@app.on_event("startup")
async def load_model():
    global model
    try:
        logger.info(f"Loading model: {MODEL_NAME}")
        model = SentenceTransformer(MODEL_NAME)
        logger.info("Model loaded successfully")
    except Exception as e:
        logger.error(f"Failed to load model: {e}")
        raise

@app.get("/")
async def root():
    return {"message": "AI Analytics Embeddings API", "model": MODEL_NAME}

@app.get("/health")
async def health():
    return {"status": "healthy", "model_loaded": model is not None}

@app.post("/embed", response_model=EmbeddingResponse)
async def get_embedding(request: TextRequest):
    if model is None:
        raise HTTPException(status_code=503, detail="Model not loaded")
    
    try:
        embedding = model.encode(request.text, convert_to_numpy=True)
        return {
            "embedding": embedding.tolist(),
            "dimension": len(embedding)
        }
    except Exception as e:
        logger.error(f"Error generating embedding: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/embed-batch", response_model=BatchEmbeddingResponse)
async def get_batch_embeddings(request: TextsRequest):
    if model is None:
        raise HTTPException(status_code=503, detail="Model not loaded")
    
    try:
        embeddings = model.encode(request.texts, convert_to_numpy=True)
        return {
            "embeddings": [emb.tolist() for emb in embeddings],
            "dimension": embeddings.shape[1] if len(embeddings.shape) > 1 else len(embeddings)
        }
    except Exception as e:
        logger.error(f"Error generating batch embeddings: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/similarity", response_model=SimilarityResponse)
async def get_similarity(request: SimilarityRequest):
    if model is None:
        raise HTTPException(status_code=503, detail="Model not loaded")
    
    try:
        embeddings = model.encode([request.text1, request.text2], convert_to_numpy=True)

        similarity = np.dot(embeddings[0], embeddings[1]) / (
            np.linalg.norm(embeddings[0]) * np.linalg.norm(embeddings[1])
        )

        is_duplicate = similarity > 0.85
        
        return {
            "similarity": float(similarity),
            "is_duplicate": is_duplicate
        }
    except Exception as e:
        logger.error(f"Error calculating similarity: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/deduplicate")
async def deduplicate_texts(request: TextsRequest, threshold: float = 0.85):
    if model is None:
        raise HTTPException(status_code=503, detail="Model not loaded")
    
    try:
        embeddings = model.encode(request.texts, convert_to_numpy=True)
        
        duplicates = []
        for i in range(len(request.texts)):
            for j in range(i + 1, len(request.texts)):
                similarity = np.dot(embeddings[i], embeddings[j]) / (
                    np.linalg.norm(embeddings[i]) * np.linalg.norm(embeddings[j])
                )
                
                if similarity > threshold:
                    duplicates.append({
                        "index1": i,
                        "index2": j,
                        "text1": request.texts[i][:100] + "..." if len(request.texts[i]) > 100 else request.texts[i],
                        "text2": request.texts[j][:100] + "..." if len(request.texts[j]) > 100 else request.texts[j],
                        "similarity": float(similarity)
                    })
        
        return {"duplicates": duplicates, "count": len(duplicates)}
    except Exception as e:
        logger.error(f"Error deduplicating texts: {e}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
