ИСПРАВЛЕНИЕ ВХОДА

Причина:
в старой базе мог сохраниться hash от другого пароля.

Что делать:
1. Распакуй архив с заменой файлов в:
   D:\xampp\htdocs\exam_analyzer\

2. Открой:
   http://localhost/exam_analyzer/reset_admin.php

3. Потом войди:
   login: superadmin
   password: superadmin123

В этой версии includes/auth.php сам сбрасывает пароль superadmin на superadmin123 при попытке входа.
