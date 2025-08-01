@echo off
chcp 65001 > nul
title Git自动提交工具

:: 检查是否在Git仓库中
git status > nul 2>&1
if %errorlevel% neq 0 (
    echo 错误：当前目录不是Git仓库！
    echo 请确保此脚本位于C:\Users\ND\Desktop\GLPI_Project\softwaremanager目录下
    pause
    exit /b 1
)

:: 获取当前分支名
for /f "tokens=*" %%a in ('git branch --show-current') do set "current_branch=%%a"

:input_message
cls
echo ======================================
echo          Git自动提交工具
echo ======================================
echo.
echo 当前分支: %current_branch%
echo.
set /p commit_msg=请输入提交备注（输入"q"退出）: 
if "%commit_msg%"=="q" exit /b 0

if "%commit_msg%"=="" (
    echo 提交备注不能为空！
    timeout /t 2 > nul
    goto input_message
)

:: 执行Git操作
echo.
echo 正在添加所有文件（包括新增、修改和删除的文件）...
git add -A 2>nul

:: 检查是否有文件被添加到暂存区
git diff --cached --quiet
if %errorlevel% equ 0 (
    echo 没有文件需要提交！请确保有修改或新增的文件。
    pause
    exit /b 1
)

echo 正在提交代码...
git commit -m "%commit_msg%"

echo 正在拉取远程更新...
git pull origin %current_branch%

echo 正在推送代码到远程仓库...
git push origin %current_branch%

echo.
echo ======================================
echo         操作完成！提交备注：
echo         %commit_msg%
echo ======================================
echo.

pause