#скрипт анализа
# !/usr/bin/env python3
import sys
import pandas as pd
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent))

from src.analyzers.question_analyzer import QuestionAnalyzer
from src.utils.data_loader import DataLoader


def main():
    data = DataLoader.load_student_data("data/raw/student_responses.csv")

    analyzer = QuestionAnalyzer()

    results = []
    for question in data.columns:
        if question.startswith('Q'):
            correct_answers = ['A', 'B']  # Заменить на реальные правильные ответы
            student_answers = data[question].to_dict()

            analysis = analyzer.analyze_question_quality(
                f"Question {question}",
                correct_answers,
                student_answers
            )

            results.append({
                "question": question,
                **analysis
            })

    results_df = pd.DataFrame(results)
    DataLoader.save_results(results_df, "data/processed/question_analysis.csv")

    print(f"Проанализировано {len(results)} вопросов")
    print("Результаты сохранены в data/processed/question_analysis.csv")


if __name__ == "__main__":
    main()