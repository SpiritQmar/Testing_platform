#анализ успеваемости студентов
import pandas as pd
import numpy as np
from typing import Dict, List


class StudentAnalyzer:
    def __init__(self):
        pass

    def calculate_correlations(self, student_responses: pd.DataFrame) -> Dict[str, float]:
        correlations = {}

        for question in student_responses.columns:
            if question != 'total_score':
                corr = student_responses[question].corr(student_responses['total_score'])
                correlations[question] = corr

        return correlations

    def identify_patterns(self, student_data: pd.DataFrame) -> Dict[str, Any]:
        results = {}

        student_data['avg_score'] = student_data.mean(axis=1)

        weak_questions = student_data.mean().sort_values().head(3).index.tolist()
        strong_questions = student_data.mean().sort_values(ascending=False).head(3).index.tolist()

        results['weak_questions'] = weak_questions
        results['strong_questions'] = strong_questions
        results['overall_avg'] = student_data['avg_score'].mean()

        return results

    def analyze_student_performance(self, student_id: str, responses: Dict[str, float],
                                    class_average: float) -> Dict[str, Any]:
        student_avg = np.mean(list(responses.values()))

        below_average = [q for q, score in responses.items()
                         if score < class_average]

        return {
            "student_id": student_id,
            "average_score": student_avg,
            "below_average_questions": below_average,
            "performance_gap": class_average - student_avg
        }