import unittest
import numpy as np
from src.analyzers.question_analyzer import QuestionAnalyzer


class TestQuestionAnalyzer(unittest.TestCase):
    def setUp(self):
        self.analyzer = QuestionAnalyzer()

    def test_question_quality(self):
        student_answers = {
            's1': 'A', 's2': 'A', 's3': 'B', 's4': 'A', 's5': 'B'
        }
        correct_answers = ['A']

        result = self.analyzer.analyze_question_quality(
            "Test question",
            correct_answers,
            student_answers
        )

        self.assertIn('difficulty', result)
        self.assertIn('quality', result)
        self.assertTrue(0 <= result['quality'] <= 1)


if __name__ == '__main__':
    unittest.main()