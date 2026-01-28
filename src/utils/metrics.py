#метрика качества
import numpy as np
from sklearn.metrics import precision_recall_fscore_support


class MetricsCalculator:
    @staticmethod
    def calculate_question_metrics(correct_answers: List[bool]) -> Dict[str, float]:
        total = len(correct_answers)
        correct = sum(correct_answers)

        difficulty = 1 - (correct / total) if total > 0 else 0

        return {
            "difficulty": difficulty,
            "correct_rate": correct / total if total > 0 else 0,
            "total_attempts": total
        }

    @staticmethod
    def calculate_correlation_matrix(data: pd.DataFrame) -> pd.DataFrame:
        return data.corr()

    @staticmethod
    def calculate_discrimination_index(high_group: List[bool], low_group: List[bool]) -> float:
        high_correct = sum(high_group) / len(high_group) if high_group else 0
        low_correct = sum(low_group) / len(low_group) if low_group else 0

        return high_correct - low_correct