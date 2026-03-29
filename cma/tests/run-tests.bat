@echo off
REM Run PHP unit tests from Windows
REM Usage: run-tests.bat [TestName] [--filter=methodName]

cd /d "%~dp0.."
php tests/TestRunner.php %*
