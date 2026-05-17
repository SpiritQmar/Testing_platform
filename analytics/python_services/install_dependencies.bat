@echo off
title UAMS AI Service - Setup
echo ============================================
echo  UAMS AI Embeddings Service - Setup
echo  Run as Administrator!
echo ============================================
echo.

:: Check Python
"C:\Python313\python.exe" --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: Python 3.13 not found at C:\Python313\python.exe
    echo Download from: https://www.python.org/downloads/
    pause
    exit /b 1
)

echo [1/2] Installing Python packages...
"C:\Python313\python.exe" -m pip install ^
    "fastapi>=0.115.0" ^
    "numpy>=2.0.0" ^
    "pydantic>=2.8.0" ^
    "sentence-transformers>=3.0.0" ^
    "uvicorn>=0.30.0" ^
    --target="C:\Python313\Lib\site-packages" --upgrade

if %errorlevel% neq 0 (
    echo ERROR: pip install failed. Make sure you run as Administrator.
    pause
    exit /b 1
)

echo.
echo [2/2] Pre-downloading AI model (paraphrase-multilingual-MiniLM-L12-v2)...
"C:\Python313\python.exe" -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2')"

echo.
echo ============================================
echo  Setup complete! Run start_service.bat to start.
echo ============================================
pause
