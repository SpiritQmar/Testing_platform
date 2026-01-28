#генератор для текста
import numpy as np
from typing import List


class EmbeddingGenerator:
    def __init__(self, model):
        self.model = model

    def generate_embeddings(self, texts: List[str]) -> np.ndarray:
        embeddings = self.model.encode(texts)
        return embeddings

    def calculate_similarity_matrix(self, texts: List[str]) -> np.ndarray:
        embeddings = self.generate_embeddings(texts)
        similarity_matrix = np.dot(embeddings, embeddings.T)
        return similarity_matrix

    def find_closest_matches(self, query: str, candidates: List[str], top_k: int = 3):
        query_embedding = self.model.encode(query)
        candidate_embeddings = self.model.encode(candidates)

        similarities = np.dot(candidate_embeddings, query_embedding)

        top_indices = np.argsort(similarities)[-top_k:][::-1]

        results = []
        for idx in top_indices:
            results.append({
                "text": candidates[idx],
                "similarity": float(similarities[idx])
            })

        return results