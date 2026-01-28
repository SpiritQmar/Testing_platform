#проверка соотвтествий по силлабусу
from sentence_transformers import util
import numpy as np


class SyllabusMatcher:
    def __init__(self, embedding_model):
        self.model = embedding_model

    def match_question_to_syllabus(self, question: str, syllabus_topics: List[str],
                                   threshold: float = 0.7) -> Dict[str, Any]:
        question_embedding = self.model.encode(question)
        topic_embeddings = self.model.encode(syllabus_topics)

        similarities = util.cos_sim(question_embedding, topic_embeddings)[0]

        max_similarity = float(torch.max(similarities))
        best_topic_idx = int(torch.argmax(similarities))
        best_topic = syllabus_topics[best_topic_idx] if syllabus_topics else ""

        matches = {
            "best_match": best_topic,
            "similarity_score": max_similarity,
            "is_valid": max_similarity >= threshold,
            "all_similarities": similarities.tolist()
        }

        return matches

    def detect_out_of_syllabus(self, questions: List[str], syllabus_topics: List[str]) -> List[Dict]:
        results = []

        for q in questions:
            match_result = self.match_question_to_syllabus(q, syllabus_topics)
            if not match_result["is_valid"]:
                results.append({
                    "question": q,
                    "match_score": match_result["similarity_score"],
                    "suggested_topic": match_result["best_match"]
                })

        return results