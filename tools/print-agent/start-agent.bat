@echo off
rem Isi URL aplikasi dan token dari halaman Admin > Printer Bar, lalu jalankan file ini.
set URL=https://GANTI-DENGAN-DOMAIN-ANDA.vercel.app
set TOKEN=GANTI-DENGAN-TOKEN
:loop
php print-agent.php --url=%URL% --token=%TOKEN% --interval=3
echo Agen berhenti, mulai ulang dalam 5 detik...
timeout /t 5 >nul
goto loop
