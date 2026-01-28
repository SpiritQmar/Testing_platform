#тесты мл моделей
import unittest
from src.ml.similarity_model import SimilarityModel


class TestSimilarityModel(unittest.TestCase):
    def test_similarity_detection(self):
        model = SimilarityModel()

        similar = model.find_similar_questions(
            "Что такое искусственный интеллект?",
            ["Что такое ИИ?", "Определение искусственного интеллекта"]
        )

        self.assertIsInstance(similar, list)


if __name__ == '__main__':
    unittest.main()