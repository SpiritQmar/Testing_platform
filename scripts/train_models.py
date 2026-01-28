#обучение мл модели
# !/usr/bin/env python3
import pandas as pd
import pickle
from pathlib import Path

sys.path.append(str(Path(__file__).parent.parent))

from src.ml.quality_predictor import QualityPredictor


def main():
    data = pd.read_csv("data/processed/training_data.csv")

    X = data[['difficulty', 'discrimination', 'correct_rate', 'syllabus_match']].values
    y = data['quality_label'].apply(lambda x: 1 if x == 'good' else 0).values

    predictor = QualityPredictor()
    predictor.model.fit(X, y)

    predictor.save_model("models/quality_predictor.pkl")

    print("Модель обучена и сохранена")


if __name__ == "__main__":
    main()