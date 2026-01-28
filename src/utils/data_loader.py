#csv данные лоадер
import pandas as pd
import json
from typing import Dict, Any


class DataLoader:
    @staticmethod
    def load_student_data(filepath: str) -> pd.DataFrame:
        return pd.read_csv(filepath)

    @staticmethod
    def load_question_bank(filepath: str) -> Dict[str, Any]:
        with open(filepath, 'r', encoding='utf-8') as f:
            return json.load(f)

    @staticmethod
    def load_syllabus(filepath: str) -> List[str]:
        with open(filepath, 'r', encoding='utf-8') as f:
            return [line.strip() for line in f.readlines()]

    @staticmethod
    def save_results(data: Any, filepath: str):
        if isinstance(data, pd.DataFrame):
            data.to_csv(filepath, index=False)
        else:
            with open(filepath, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)