import unittest
from unittest.mock import patch

import numpy as np
from fastapi.testclient import TestClient

from app.main import app


class FakeModel:
    def encode(self, value, convert_to_numpy=True):
        if isinstance(value, str):
            return np.array([1.0, 0.0, 0.0], dtype=float)

        vectors = {
            "alpha": np.array([1.0, 0.0, 0.0], dtype=float),
            "alpha copy": np.array([0.99, 0.01, 0.0], dtype=float),
            "beta": np.array([0.0, 1.0, 0.0], dtype=float),
            "zero": np.array([0.0, 0.0, 0.0], dtype=float),
        }
        return np.array([vectors[item] for item in value], dtype=float)


class ApiTests(unittest.TestCase):
    def setUp(self):
        self.load_model_patcher = patch("app.main.load_model", return_value=None)
        self.model_patcher = patch("app.services.loaded_model", FakeModel())
        self.load_model_patcher.start()
        self.model_patcher.start()
        self.client = TestClient(app)

    def tearDown(self):
        self.model_patcher.stop()
        self.load_model_patcher.stop()

    def test_ready_reports_loaded_model(self):
        response = self.client.get("/ready")

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()["model_loaded"], True)

    def test_live_reports_process_status(self):
        response = self.client.get("/live")

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()["status"], "alive")

    def test_embed_returns_vector(self):
        response = self.client.post("/embed", json={"text": "hello"})

        self.assertEqual(response.status_code, 200)
        payload = response.json()
        self.assertEqual(payload["dimension"], 3)
        self.assertEqual(payload["embedding"], [1.0, 0.0, 0.0])

    def test_similarity_handles_zero_norm(self):
        response = self.client.post(
            "/similarity",
            json={"text1": "zero", "text2": "alpha"},
        )

        self.assertEqual(response.status_code, 200)
        payload = response.json()
        self.assertEqual(payload["similarity"], 0.0)
        self.assertEqual(payload["is_duplicate"], False)

    def test_similarity_with_blank_text_returns_zero(self):
        response = self.client.post(
            "/similarity",
            json={"text1": "   ", "text2": "alpha"},
        )

        self.assertEqual(response.status_code, 200)
        payload = response.json()
        self.assertEqual(payload["similarity"], 0.0)
        self.assertEqual(payload["is_duplicate"], False)

    def test_similarity_with_missing_fields_returns_zero(self):
        response = self.client.post("/similarity", json={})

        self.assertEqual(response.status_code, 200)
        payload = response.json()
        self.assertEqual(payload["similarity"], 0.0)
        self.assertEqual(payload["is_duplicate"], False)

    def test_deduplicate_returns_duplicates(self):
        response = self.client.post(
            "/deduplicate",
            json={"texts": ["alpha", "alpha copy", "beta"], "threshold": 0.95},
        )

        self.assertEqual(response.status_code, 200)
        payload = response.json()
        self.assertEqual(payload["count"], 1)
        self.assertEqual(payload["duplicates"][0]["index1"], 0)
        self.assertEqual(payload["duplicates"][0]["index2"], 1)

    def test_validation_rejects_blank_text(self):
        response = self.client.post("/embed", json={"text": "   "})

        self.assertEqual(response.status_code, 422)

    def test_validation_rejects_out_of_range_threshold(self):
        response = self.client.post(
            "/deduplicate",
            json={"texts": ["alpha", "beta"], "threshold": 1.5},
        )

        self.assertEqual(response.status_code, 422)

    def test_deduplicate_accepts_threshold_from_query(self):
        response = self.client.post(
            "/deduplicate?threshold=0.95",
            json={"texts": ["alpha", "alpha copy", "beta"]},
        )

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()["count"], 1)


class StartupTests(unittest.TestCase):
    def test_startup_fails_when_model_cannot_be_loaded(self):
        with patch("app.main.load_model", side_effect=RuntimeError("boom")):
            with self.assertRaises(RuntimeError):
                with TestClient(app):
                    pass


if __name__ == "__main__":
    unittest.main()
