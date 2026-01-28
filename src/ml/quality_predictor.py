#качество вопросов предсказатель
from sklearn.ensemble import RandomForestClassifier
import numpy as np
import pickle


class QualityPredictor:
    def __init__(self):
        self.model = RandomForestClassifier(n_estimators=100, random_state=42)
        self.features = []
        self.labels = []

    def extract_features(self, question_data: Dict[str, Any]) -> np.ndarray:
        features = []

        features.append(question_data.get('difficulty', 0))
        features.append(question_data.get('discrimination', 0))
        features.append(question_data.get('correct_rate', 0))
        features.append(question_data.get('syllabus_match', 0))
        features.append(question_data.get('length', 0))
        features.append(question_data.get('bias_score', 0))

        return np.array(features).reshape(1, -1)

    def predict_quality(self, question_data: Dict) -> Dict:
        features = self.extract_features(question_data)

        prediction = self.model.predict(features)[0]
        probability = self.model.predict_proba(features)[0][1]

        return {
            "quality_label": "good" if prediction == 1 else "bad",
            "quality_score": probability,
            "recommendation": "Сохранить" if prediction == 1 else "Пересмотреть"
        }

    def save_model(self, path: str):
        with open(path, 'wb') as f:
            pickle.dump(self.model, f)

    def load_model(self, path: str):
        with open(path, 'rb') as f:
            self.model = pickle.load(f)