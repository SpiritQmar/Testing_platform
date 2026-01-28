#анализатор вопросов
import numpy as np
from typing import Dict, Any, List
from sklearn.metrics import accuracy_score


class QuestionAnalyzer:
    def __init__(self):
        self.metrics = {}

    def analyze_question_quality(self, question_text: str, correct_answers: List[str],
                                 student_answers: Dict[str, str]) -> Dict[str, float]:
        total_students = len(student_answers)
        if total_students == 0:
            return {"difficulty": 0.0, "discrimination": 0.0, "quality": 0.0}

        correct_count = sum(1 for answer in student_answers.values()
                            if answer in correct_answers)
        difficulty = 1 - (correct_count / total_students)

        top_30 = int(total_students * 0.3)
        bottom_30 = int(total_students * 0.3)

        sorted_students = sorted(student_answers.items(),
                                 key=lambda x: x[1] in correct_answers, reverse=True)

        top_correct = sum(1 for _, ans in sorted_students[:top_30]
                          if ans in correct_answers)
        bottom_correct = sum(1 for _, ans in sorted_students[-bottom_30:]
                             if ans in correct_answers)

        discrimination = (top_correct / top_30) - (bottom_correct / bottom_30)

        quality = 0.6 * (1 - difficulty) + 0.4 * discrimination

        return {
            "difficulty": difficulty,
            "discrimination": discrimination,
            "quality": quality,
            "correct_rate": correct_count / total_students
        }

    def check_syllabus_match(self, question: str, syllabus_keywords: List[str]) -> float:
        question_lower = question.lower()
        keywords_found = sum(1 for kw in syllabus_keywords
                             if kw.lower() in question_lower)
        return keywords_found / len(syllabus_keywords) if syllabus_keywords else 0.0