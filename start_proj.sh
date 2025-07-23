#WINDOWS
#@echo off
#cd /d %~dp0
#start http://localhost:8000/
#php -S localhost:8000

#LINUX:
cd "$(dirname "$0")"
php -S localhost:8000 &
sleep 1
xdg-open http://localhost:8000/
