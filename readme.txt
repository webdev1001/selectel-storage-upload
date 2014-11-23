=== Selectel Storage Upload ===
Contributors: Mauhem
Donate link: http://wm-talk.net/supload-wordpress-plagin-dlya-zagruzki-na-selectel
Tags: Uploads, Selectel, CDN, Cloud, Storage, media library, OpenStack, Object storage
Requires at least: 3.0.1
Tested up to: 4.0
Stable tag: 1.2.3
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin allows you to automatically synchronize media files that are downloaded to the articles or just the library, to Selectel Storage.

== Description ==
This plugin allows you to synchronize files that are uploaded from the media library Wordpress with Selectel Storage (or othet OpenStack Object Storage). Synchronization takes place either in an automatic mode (at upload time) or manually. Supported function to delete files from Selectel Storage when they are removed from the library.
This plugin allows you to securely store files, and save significant site traffic if you use a domain / subdomain with public bucket.

In Russian:<br />
Этот плагин позволяет синхронизировать файлы, загруженные из медиа-библиотеки Wordpress  в облачное хранилище Selectel (или любой другой OpenStack Object Storage). Синхронизация происходит либо в автоматическом режиме (на этапе загрузки), либо вручную. Поддерживается функция удаления файлов из облачного хранилища Selectel, когда они удаляются из библиотеки.<br />
Этот плагин позволяет безопасно хранить файлы, и значительно сэкономить трафик и деньги, затрачиваемые на хранение файлов, если использовать домен/поддомен и публичный контейнер.<br />

== Installation ==

1. Upload plugin directory to the `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings -> Selectel Upload and set up the plugin

In Russian:<br />
1. Загрузить плагин в каталог `/wp-content/plugins/`<br />
2. Активировать плагин через меню 'Плагины' в WordPress<br />
3. Перейдите в раздел Настройки -> Selectel Upload и настроить плагин<br />

== Frequently Asked Questions ==
* <strong>Error 403 Forbidden</strong><br />
Проверьте логин/пароль и указанный сервер авторизациии на корректность

* <strong>Stream open failed for 'https://auth.selcdn.ru:443/'</strong><br />
Проблема с подключением к хранилищу.  Проверьте сетевое подлючение.

* <strong>Impossible to upload a file</strong><br />
Проблема с сохранением файла в облачном хранилище. Проверьте сетевое подлючение.

* <strong>Do not have access to the file</strong><br />
Проблема с чтением файла. Проверьте существует ли файл и права доступа к нему.

* <strong>Invalid response from the authentication service.</strong><br />
Возмодны два варианта:
1. Хостер блокирует доступ к серверу Selectel (по умолчанию: auth.selcdn.ru, порт 443). Обратитесь к техподдержке хостера.
2. Проблема на сервере Selectel. Просто подождите, либо сообщите об ошиббке техподдержке.

== Changelog ==
<a href="https://github.com/Mauhem/selectel-storage-upload">Commit Log</a><br />

<strong>b5fcc6a</strong> Исправлена ошибка JS при отложенной загрузке JQuery.<br />
<strong>1cb9c14</strong> Заменена библиотека загрузки в хранилище на стороннюю. Более информативные сообщения об ошибках. Корректное удаление файлов из хранилица.<br />
<strong>7cca652</strong></strong> Проверять подключение можно на лету, без сохранения нстроек. Откорректированы имена переменных. Приведены к общему виду.<br />
<strong>746e122</strong> Вывод ошибки подключения к контейнеру при ручной синхронизации. Откорректированы иена переменных для улучшения совместимости<br />
<strong>235d1ba</strong> Добавление доп. проверки загрузки файла в хранилище. Удаление пробелов из логина/пароля Вывод ошибки при недоступности каталога с файлами для чтения. Откорректированы иена переменных для улучшения совместимости<br />
<strong>e6ae025</strong> Релиз плагина версии 1.1.0<br />
<strong>5423921</strong> Чистка js кода. Корректировка проверки доступности файлов для чтения.<br />
<strong>1963eb2</strong> Добавлен обработчик ошибок ручной синхронизации. Исправлены ошибка синхронизации файлов с пробелом в имени.<br />
<strong>e5717d7</strong> Плагину больше не нужна Zlib расширение. Пробное решение.<br />
<strong>bc369bf</strong> Устранения бага с удалением файлов. Одиночной синхронизацией. Значительные изменения механизма работы.<br />
<strong>f041e7f</strong> Удаление неиспользовавшихся переменных. Незначительные правки<br />
<strong>9007ba4</strong> Исправление бага с сохранением настроек, удалением временного файла. Добавление проверки на совместимость.<br />
<strong>f54aff4</strong> Fix permission<br />
<strong>29b951c</strong> Фикс open_basedir и мелких недоработок<br />
<strong>6e0cd52</strong> Fix path to images<br />
<strong>fdb62ed</strong> Изменение имен функций и классов для совместимости. + белорусский язык<br />
<strong>9b5d5b2</strong> First public release<br />