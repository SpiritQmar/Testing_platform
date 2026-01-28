from sentence_transformers import SentenceTransformer
import torch


class ModelLoader:
    def __init__(self, model_name="sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2"):
        self.model = None
        self.model_name = model_name

    def load(self):
        if self.model is None:
            self.model = SentenceTransformer(self.model_name)
        return self.model