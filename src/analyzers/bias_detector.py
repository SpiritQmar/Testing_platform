#детект смещения в вопросах
import re
from typing import List, Dict


class BiasDetector:
    def __init__(self):
        self.bias_patterns = {
            'gender': r'\b(мужчин|женщин|парень|девушка|он|она)\b',
            'nationality': r'\b(русск|украин|татар|еврей|армян)\b',
            'cultural': r'\b(запад|восток|европ|азиат)\b'
        }

    def detect_bias(self, text: str) -> Dict[str, Any]:
        results = {}

        for bias_type, pattern in self.bias_patterns.items():
            matches = re.findall(pattern, text, re.IGNORECASE)
            if matches:
                results[bias_type] = {
                    "count": len(matches),
                    "matches": matches[:3]
                }

        results['has_bias'] = len(results) > 0
        results['bias_score'] = len(results) / len(self.bias_patterns)

        return results

    def analyze_question_fairness(self, question: str, student_responses: Dict[str, Dict]) -> Dict:
        bias_result = self.detect_bias(question)

        if not bias_result['has_bias']:
            return {"fair": True, "bias_types": []}

        bias_types = list(bias_result.keys())

        return {
            "fair": False,
            "bias_types": bias_types,
            "recommendation": "Перефразировать вопрос, уб biased выражения"
        }