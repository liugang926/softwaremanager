@echo off
chcp 65001 > nul
title Git�Զ��ύ����

:: ����Ƿ���Git�ֿ���
git status > nul 2>&1
if %errorlevel% neq 0 (
    echo ���󣺵�ǰĿ¼����Git�ֿ⣡
    echo ��ȷ���˽ű�λ��C:\Users\ND\Desktop\GLPI_Project\softwaremanagerĿ¼��
    pause
    exit /b 1
)

:: ��ȡ��ǰ��֧��
for /f "tokens=*" %%a in ('git branch --show-current') do set "current_branch=%%a"

:input_message
cls
echo ======================================
echo          Git�Զ��ύ����
echo ======================================
echo.
echo ��ǰ��֧: %current_branch%
echo.
set /p commit_msg=�������ύ��ע������"q"�˳���: 
if "%commit_msg%"=="q" exit /b 0

if "%commit_msg%"=="" (
    echo �ύ��ע����Ϊ�գ�
    timeout /t 2 > nul
    goto input_message
)

:: ִ��Git����
echo.
echo ������������ļ��������������޸ĺ�ɾ�����ļ���...
git add -A 2>nul

:: ����Ƿ����ļ�����ӵ��ݴ���
git diff --cached --quiet
if %errorlevel% equ 0 (
    echo û���ļ���Ҫ�ύ����ȷ�����޸Ļ��������ļ���
    pause
    exit /b 1
)

echo �����ύ����...
git commit -m "%commit_msg%"

echo ������ȡԶ�̸���...
git pull origin %current_branch%

echo �������ʹ��뵽Զ�ֿ̲�...
git push origin %current_branch%

echo.
echo ======================================
echo         ������ɣ��ύ��ע��
echo         %commit_msg%
echo ======================================
echo.

pause