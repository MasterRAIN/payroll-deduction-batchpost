@echo off

cd C:\xampp\htdocs\payroll-deduction\src

echo.
echo This script will upload Payroll Deduction Payments.
echo Please confirm if you want to proceed
echo.
choice /M "Do you want to continue? (Yes/No)"

if errorlevel 2 (
  echo Operation canceled.
  pause
  exit
)

echo.
    php artisan payrolldeduction:pay
echo.
echo.
echo.
     pause
