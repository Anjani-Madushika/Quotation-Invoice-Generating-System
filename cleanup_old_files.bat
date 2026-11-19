@echo off
rem Run this ONCE (double-click) inside the quotation_system folder.
rem It removes old copies of the pages from the root folder. The real pages live in the "pages" folder.
cd /d "%~dp0"
echo.
echo Removing old page files from the root folder...
echo (index.php and login.php are kept. Only files that also exist inside the pages folder are deleted.)
echo.
for %%F in (pages\*.php) do (
  if /I not "%%~nxF"=="index.php" if /I not "%%~nxF"=="login.php" (
    if exist "%%~nxF" (
      del /q "%%~nxF"
      echo Deleted %%~nxF
    )
  )
)
for /d %%D in ("{*") do (
  rd /s /q "%%D"
  echo Deleted folder %%D
)
echo.
echo Done. You can delete this file (cleanup_old_files.bat) now.
echo Open the site at http://localhost/quotation_system/
pause
