#детектор дубликатов вопросов
import torch
from sentence_transformers import SentenceTransformer, util


class SimilarityModel:
    def __init__(self, model_name="sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"):
        self.model = SentenceTransformer(model_name)
        self.threshold = 0.85

    def find_similar_questions(self, new_question: str, existing_questions: List[str]) -> List[Dict]:
        new_embedding = self.model.encode(new_question)
        existing_embeddings = self.model.encode(existing_questions)

        similarities = util.cos_sim(new_embedding, existing_embeddings)[0]

        similar = []
        for idx, sim in enumerate(similarities):
            if sim > self.threshold:
                similar.append({
                    "question": existing_questions[idx],
                    "similarity": float(sim),
                    "is_duplicate": sim > 0.9
                })

        return sorted(similar, key=lambda x: x["similarity"], reverse=True)

    def detect_rephrased_rejected(self, question: str, rejected_questions: List[str]) -> bool:
        similar = self.find_similar_questions(question, rejected_questions)

        if similar and similar[0]["similarity"] > 0.88:
            return True

        return False