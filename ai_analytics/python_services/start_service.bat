@echo off
title AI Embeddings Service
cd /d "C:\xampp\htdocs\uams\ai_analytics\python_services"
echo Starting AI Embeddings Service...
echo.
"C:\Python313\python.exe" embeddings_api.py
echo.
echo Service stopped. Press any key to close.
pause >nul
