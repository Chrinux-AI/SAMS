@echo off
REM ==============================================================
REM Academic Sentinel - Local AI Server Launcher
REM 
REM This script initializes the local AI server pointing EXACTLY
REM to your downloaded GGUF file without copying or moving it,
REM preserving your remaining 29GB of disk storage.
REM ==============================================================

set MODEL_PATH="%USERPROFILE%\Downloads\AI\gpt-oss-20b-UD-Q8_K_XL_2.gguf"

if not exist %MODEL_PATH% (
    echo Error: The specified AI model does not exist at %MODEL_PATH%.
    echo Please make sure the file is located exactly in your Downloads folder.
    pause
    exit /b 1
)

echo.
echo [Sentinel AI] Initializing Local Inference Engine...
echo [Sentinel AI] Loading absolute path: %MODEL_PATH%
echo [Sentinel AI] Host: localhost:8080
echo.

REM To run this, you need llama-server.exe (from Llama.cpp) installed.
REM If you have LM Studio, you can just start its "Local Server" feature on port 1234
REM and skip this batch file entirely.

llama-server.exe -m %MODEL_PATH% --port 8080 -c 4096 --n-gpu-layers 35

pause
