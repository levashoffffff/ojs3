# Деплой OJS на VPS + CI/CD

## Что такое CI/CD простыми словами

**CI/CD** (Continuous Deployment) — это когда ты правишь код локально,
делаешь `git push`, и сервер **автоматически** обновляется.
Никакого ручного копирования файлов.

```
[Твой ноутбук]  →  git push  →  [VPS сервер]
                                      ↓
                               post-receive hook
                                      ↓
                              файлы обновились
                              кеши очищены
                              PHP перезапущен
```

---

## Сервер

| Параметр | Значение |
|---|---|
| IP | 88.218.120.239 |
| OS | Ubuntu |
| Web-сервер | Nginx |
| PHP | PHP-FPM 8.3 |
| БД | MySQL |

---

## Часть 1 — Первоначальный деплой (делается один раз)

### 1.1 Локально — готовим проект

```bash
cd e:/job/projects/ojs3

# Добавляем все изменённые файлы в git
git add .

# Сохраняем изменения с описанием
git commit -m "Initial deploy"

# Экспортируем локальную БД в файл
mysqldump -u root -p --single-transaction ojsc > ojsc_deploy.sql
# --single-transaction = корректный экспорт без блокировок таблиц
# ojsc = имя базы данных
# > ojsc_deploy.sql = сохранить в файл
```

### 1.2 Локально — добавляем сервер как git-remote

```bash
# git remote add — добавить адрес удалённого репозитория
# vps — короткое имя (можно любое)
# ssh://root@IP/путь — адрес bare-репозитория на сервере
git remote add vps ssh://root@88.218.120.239/var/repo/ojs.git
```

### 1.3 На сервере — подключаемся

```bash
ssh root@88.218.120.239
# ssh — протокол безопасного подключения к удалённому серверу
# root — имя пользователя
# 88.218.120.239 — IP адрес сервера
```

### 1.4 На сервере — чистим старую установку

```bash
rm -rf /var/www/ojs        # удалить папку со старым OJS
rm -rf /var/repo/ojs.git   # удалить старый git-репозиторий (если был)
# rm = remove (удалить)
# -r = рекурсивно (включая вложенные папки)
# -f = force (без подтверждения)
```

### 1.5 На сервере — создаём базу данных

```bash
mysql -u root -p
# -u root = пользователь root
# -p = запросить пароль
```

```sql
-- Удаляем старую БД если была
DROP DATABASE IF EXISTS ojsc;

-- Создаём новую с правильной кодировкой (важно для кириллицы!)
CREATE DATABASE ojsc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Создаём пользователя для OJS
CREATE USER IF NOT EXISTS 'ojsuser'@'localhost' IDENTIFIED BY 'Ojs2024';

-- Даём ему все права на нашу БД
GRANT ALL PRIVILEGES ON ojsc.* TO 'ojsuser'@'localhost';

-- Применяем изменения прав
FLUSH PRIVILEGES;
EXIT;
```

### 1.6 На сервере — создаём папки

```bash
mkdir -p /var/www/ojs       # папка для файлов сайта
mkdir -p /var/ojs_files     # папка для загруженных файлов (статьи, обложки)
mkdir -p /var/repo/ojs.git  # папка для git-репозитория

# mkdir = make directory (создать папку)
# -p = создать все промежуточные папки если не существуют
```

### 1.7 На сервере — инициализируем git bare-репозиторий

```bash
cd /var/repo/ojs.git
git init --bare
# bare-репозиторий = только история git, без рабочих файлов
# именно в него мы будем делать git push с локальной машины
```

### 1.8 На сервере — создаём deploy hook

Это скрипт который запускается **автоматически** после каждого `git push`:

```bash
cat > /var/repo/ojs.git/hooks/post-receive << 'EOF'
#!/bin/bash
echo "→ Deploying..."

# Копируем файлы из git-репозитория в папку сайта
GIT_WORK_TREE=/var/www/ojs GIT_DIR=/var/repo/ojs.git git checkout -f main

# Очищаем кеши OJS (скомпилированные шаблоны и стили)
echo "→ Clearing caches..."
rm -rf /var/www/ojs/cache/t_compile/*
rm -rf /var/www/ojs/cache/opcache/*
find /var/www/ojs/cache -name "*.css" -delete 2>/dev/null

# Устанавливаем правильного владельца файлов (www-data = пользователь nginx/php)
echo "→ Setting permissions..."
chown -R www-data:www-data /var/www/ojs
chown -R www-data:www-data /var/ojs_files

# Перезапускаем PHP чтобы подхватились изменения
systemctl restart php8.3-fpm

echo "✓ Deploy complete!"
EOF

# Делаем скрипт исполняемым
chmod +x /var/repo/ojs.git/hooks/post-receive
```

### 1.9 На сервере — настраиваем Nginx

```bash
cat > /etc/nginx/sites-available/ojs << 'EOF'
server {
    listen 80;           # слушаем порт 80 (HTTP)
    server_name _;       # _ означает "любой хост"
    root /var/www/ojs;   # корневая папка сайта
    index index.php;
    client_max_body_size 100M;  # максимальный размер загружаемых файлов

    access_log /var/log/nginx/ojs_access.log;  # лог всех запросов
    error_log /var/log/nginx/ojs_error.log;    # лог ошибок

    # Все запросы → пробуем найти файл → иначе передаём в index.php
    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    # PHP файлы обрабатывает PHP-FPM
    location ~ ^(.+\.php)(.*)$ {
        fastcgi_split_path_info ^(.+\.php)(.*)$;
        set $path_info $fastcgi_path_info;
        fastcgi_param PATH_INFO $path_info;
        fastcgi_param PATH_TRANSLATED $document_root$path_info;
        if (!-f $document_root$fastcgi_script_name) { return 404; }
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Запрещаем доступ к скрытым файлам (.git, .env и т.д.)
    location ~ /\.(?!well-known).* { deny all; }

    # Статические файлы кешируем на 30 дней в браузере
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 30d;
        access_log off;
        add_header Cache-Control "public";
    }
}
EOF

# Активируем конфиг (создаём символическую ссылку)
ln -sf /etc/nginx/sites-available/ojs /etc/nginx/sites-enabled/ojs

# Удаляем дефолтный сайт nginx
rm -f /etc/nginx/sites-enabled/default

# Проверяем конфиг на ошибки
nginx -t

# Перезапускаем nginx
systemctl restart nginx
```

### 1.10 Локально — пушим файлы на сервер

```bash
# Отправляем все закоммиченные файлы на сервер
# После этого автоматически запустится post-receive hook
git push vps main

# Должно появиться:
# → Deploying...
# → Clearing caches...
# → Setting permissions...
# ✓ Deploy complete!
```

### 1.11 Локально — закидываем БД

```bash
# scp = secure copy (копирование через SSH)
scp ojsc_deploy.sql root@88.218.120.239:/tmp/
```

### 1.12 На сервере — импортируем БД

```bash
mysql -u ojsuser -pOjs2024 ojsc < /tmp/ojsc_deploy.sql
# < ojsc_deploy.sql = читать команды из файла
```

### 1.13 На сервере — копируем config.inc.php

```bash
# Локально:
scp e:/job/projects/ojs3/config.inc.php root@88.218.120.239:/var/www/ojs/config.inc.php
```

```bash
# На сервере — правим под продакшн:
nano /var/www/ojs/config.inc.php
```

Меняем эти параметры:
```ini
base_url = "http://88.218.120.239"
allowed_hosts = '["88.218.120.239"]'
files_dir = /var/ojs_files

; Секция [database]:
username = ojsuser
password = Ojs2024
name = ojsc
```

### 1.14 На сервере — устанавливаем Composer-зависимости (один раз)

```bash
cd /var/www/ojs/lib/pkp
composer install --no-dev --optimize-autoloader
# --no-dev = не устанавливать пакеты для разработки
# --optimize-autoloader = оптимизировать автозагрузку классов
```

### 1.15 На сервере — финальные права и перезапуск

```bash
chown -R www-data:www-data /var/www/ojs
find /var/www/ojs -type d -exec chmod 755 {} \;
find /var/www/ojs -type f -exec chmod 644 {} \;
# 755 = владелец может всё, остальные только читают и входят в папку
# 644 = владелец может читать/писать, остальные только читают

systemctl restart php8.3-fpm nginx
```

---

## Часть 2 — Ежедневная работа (CI/CD)

После первоначального деплоя всё просто:

```bash
# 1. Поправил файлы темы/шаблонов локально

# 2. Добавить изменённые файлы
git add .

# 3. Сохранить с описанием что поменял
git commit -m "Fix footer styles"

# 4. Отправить на сервер — всё остальное автоматически
git push vps main
```

### Если правил Vue-компоненты (lib/ui-library/)

После изменений в `.vue` файлах нужно пересобрать JS:

```bash
npm run build
```

Потом деплоить как обычно через `git push vps main`.
При деплое через SCP вручную копировать:

```bash
scp js/build.js root@88.218.120.239:/var/www/ojs/js/build.js
scp styles/build.css root@88.218.120.239:/var/www/ojs/styles/build.css
```

---

## Часть 3 — Полезные команды

### Просмотр логов на сервере
```bash
# Ошибки nginx
tail -f /var/log/nginx/ojs_error.log

# Все запросы к сайту
tail -f /var/log/nginx/ojs_access.log

# tail -f = выводить последние строки и обновлять в реальном времени
```

### Ручная очистка кешей OJS
```bash
rm -rf /var/www/ojs/cache/t_compile/*
rm -rf /var/www/ojs/cache/opcache/*
find /var/www/ojs/cache -name "*.css" -delete
systemctl restart php8.3-fpm
```

### Перезапуск сервисов
```bash
systemctl restart nginx          # перезапустить веб-сервер
systemctl restart php8.3-fpm    # перезапустить PHP
systemctl restart mysql          # перезапустить базу данных
```

### Статус сервисов
```bash
systemctl status nginx
systemctl status php8.3-fpm
systemctl status mysql
```

### Посмотреть сколько места занято
```bash
df -h              # место на диске
du -sh /var/www/ojs   # размер папки OJS
```

---

## Что НЕ попадает на сервер через git push

| Файл/папка | Почему |
|---|---|
| `config.inc.php` | Содержит пароли БД, копируется вручную один раз |
| `lib/pkp/lib/vendor/` | Composer-зависимости, устанавливаются на сервере один раз |
| `lib/pkp/styles/tinymce/` | В `.gitignore` — копируется вручную один раз |
| `cache/*` | Генерируется автоматически |
| `*.sql` | Файлы БД не нужны в git |
| `public/` | Загруженные пользователями файлы |

---

## Первый деплой — дополнительные файлы (вручную)

После `git push vps main` нужно отдельно скопировать файлы из `.gitignore`:

```bash
# Стили TinyMCE (редактор полей формы подачи статьи)
scp -r lib/pkp/styles/tinymce root@88.218.120.239:/var/www/ojs/lib/pkp/styles/
```

Без этого поля на странице подачи статьи (`/submission`) будут недоступны для ввода.

---

## Настройка SMTP (Яндекс Почта)

В файле `/var/www/ojs/config.inc.php` секция `[email]`:

```ini
default = smtp
smtp = On
smtp_server = smtp.yandex.ru
smtp_port = 465
smtp_auth = ssl
smtp_username = artur.levashov@yandex.ru
smtp_password = <пароль приложения из настроек Яндекс>
allow_envelope_sender = On
default_envelope_sender = artur.levashov@yandex.ru
force_default_envelope_sender = On
force_dmarc_compliant_from = On
```

**Важно:** `force_dmarc_compliant_from = On` обязательно — без него Яндекс отклоняет
письма с ошибкой `550 5.7.0 Sender address rejected: not owned by authorized user`.
Эта опция принудительно подставляет `default_envelope_sender` в заголовок `From:`.

**Пароль приложения** создаётся в Яндекс Почте:
Настройки → Безопасность → Пароли приложений → Создать.
Обычный пароль от аккаунта не подходит.

**Контактный email журнала** в БД должен совпадать с `smtp_username`:
```bash
sudo mysql ojsc -e "UPDATE journal_settings SET setting_value = 'artur.levashov@yandex.ru' WHERE setting_name = 'contactEmail';"
```

Или через админку: Настройки → Журнал → Контакт.

После изменения конфига очистить кэш:
```bash
rm -rf /var/www/ojs/cache/fc-*
sudo systemctl restart php8.3-fpm
```

---

## Настройка подтверждения email при регистрации

В `/var/www/ojs/config.inc.php` секция `[general]`:

```ini
require_validation = On
```

При `Off` пользователи регистрируются без подтверждения email и письмо не отправляется.

---

## Решённые проблемы / грабли

### Поля формы подачи статьи недоступны для ввода

**Симптом:** на странице `/submission` поле "Название" и другие поля серые, ввод невозможен.
В консоли браузера ошибки:
```
Таблица стилей .../lib/pkp/styles/tinymce/skin.min.css не была загружена,
потому что её MIME-тип («text/html») не «text/css»
```

**Причина:** `lib/pkp/styles/tinymce/` находится в `lib/pkp/.gitignore` и не деплоится через git.
TinyMCE не инициализируется без своих CSS-файлов.

**Исправление:**
```bash
scp -r lib/pkp/styles/tinymce root@88.218.120.239:/var/www/ojs/lib/pkp/styles/
```

---

### Пустые метки в выпадающих меню (dropdown)

**Симптом:** кнопки "..." открывают тень, но пункты меню пустые или не видны.

**Диагностика:** в консоли браузера (F12):
```js
Object.keys(pkp.localeKeys).length  // если 0 — проблема с переводами
```

**Причина:** на сервере отсутствовал файл `registry/uiLocaleKeysBackend.json`.
Этот файл содержит список всех ключей переводов, которые PHP отдаёт Vue через
эндпоинт `_i18n/ui.js`. Без него `pkp.localeKeys = {}` и все метки пустые.

**Исправление:**
```bash
# Загрузить файл на сервер
scp registry/uiLocaleKeysBackend.json root@88.218.120.239:/var/www/ojs/registry/uiLocaleKeysBackend.json

# Очистить кеш переводов на сервере
ssh root@88.218.120.239 "rm -rf /var/www/ojs/cache/opcache/* && systemctl restart php8.3-fpm"
```

После этого жёсткий рефреш страницы: `Ctrl+Shift+R`.

> При деплое через `git push vps main` файл попадает на сервер автоматически.
> Проблема возникает только при ручном SCP, когда копируют файлы выборочно.

---

### Яндекс SMTP: ошибка «Sender address rejected»

**Симптом:** письма не отправляются, в логе `/var/log/fpm-php.www.log`:
```
550 5.7.0 Sender address rejected: not owned by authorized user
```

**Причина:** Яндекс требует чтобы заголовок `From:` в письме совпадал с аутентифицированным
аккаунтом. OJS по умолчанию ставит в `From:` контактный email журнала, а `force_default_envelope_sender`
меняет только envelope sender, но не `From:`.

**Исправление:** добавить в `config.inc.php`:
```ini
force_dmarc_compliant_from = On
```
Эта опция перемещает оригинальный `From:` в `Reply-To:` и подставляет `default_envelope_sender`.

---

## Структура проекта

```
/var/www/ojs/          — файлы сайта (из git)
/var/ojs_files/        — загруженные файлы журнала
/var/repo/ojs.git/     — git bare-репозиторий (куда делаем push)
/etc/nginx/sites-available/ojs  — конфиг nginx
/var/log/nginx/        — логи
```
