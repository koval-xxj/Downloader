# FileDownloader

## Структура

* **[download.php](download.php)** — скрипт для запуска

* **[classes](classes/):**
   * **[Downloader.php](classes/Downloader.php):** - Основной класс, скрипт для скачивания файла
   * **[Notifier.php](classes/Notifier.php):** - Вывод уведомления в консоль
   * **[ProgressBar.php](classes/ProgressBar.php):** - Прогресс бар


## Использование

Запустить файл download.php из консоли с обязательными параметрами -u, -s.
Где:
  * -u - url на удаленный файл
  * -s - загрузить файл в указанное к-во потоков. По умолчанию - 4

  Пример:

    > php download.php -u http://ukrposhta.ua/postindex/upload/postvpz.zip -s 4
