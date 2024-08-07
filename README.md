<h1>Инструкция по работе с сервисом мониторинга сайтов госзакупок</h1>
<h2>Ссылки и параметры парсинга</h2>
<p>
    Парсинг производится с 3 сайтов:
    <ul>
        <li>https://icetrade.by</li>
        <li>https://goszakupki.by</li>
        <li>https://gias.by</li>
    </ul>
    По следующим параметрам:
    <ul>
        <li>https://icetrade.by/search/auctions?search_text=&zakup_type%5B1%5D=1&zakup_type%5B2%5D=1&auc_num=&okrb=10.5&company_title=&establishment=0&industries=&period=&created_from=&created_to=&request_end_from=&request_end_to=&r%5B1%5D=1&r%5B2%5D=2&r%5B7%5D=7&r%5B3%5D=3&r%5B4%5D=4&r%5B6%5D=6&r%5B5%5D=5&sort=num%3Adesc&sbm=1&onPage=100</li>
        <li>https://goszakupki.by/tenders/posted?TendersSearch%5Bnum%5D=&TendersSearch%5BiceGiasNum%5D=&TendersSearch%5Btext%5D=&TendersSearch%5Bunp%5D=&TendersSearch%5Bcustomer_text%5D=&TendersSearch%5BunpParticipant%5D=&TendersSearch%5Bparticipant_text%5D=&TendersSearch%5Bprice_from%5D=&TendersSearch%5Bprice_to%5D=&TendersSearch%5Bcreated_from%5D=&TendersSearch%5Bcreated_to%5D=&TendersSearch%5Brequest_end_from%5D=&TendersSearch%5Brequest_end_to%5D=&TendersSearch%5Bauction_date_from%5D=&TendersSearch%5Bauction_date_to%5D=&TendersSearch%5Bindustry%5D=345%2C347%2C351%2C352&TendersSearch%5Btype%5D=&TendersSearch%5Bstatus%5D=&TendersSearch%5Bstatus%5D%5B%5D=Submission&TendersSearch%5Bregion%5D=&TendersSearch%5Bappeal%5D=&TendersSearch%5Bfunds%5D=</li>
        <li>https://gias.by/search/api/v1/search/purchases</li>
    </ul>
    * параметры для сайта gias.by задаются с помощью API, в отличие от остальных сайтов, где параметры заданы в url
</p>
<h2>Описание сервиса</h2>
<p>
    Запуск парсинга настроен на запуск ежедневно в 03:30 через планировщик Laravel. Изменить время можно в файле app\Console\Kernel.php (часовой пояс на сервере UTC+0 т.е. текущее -3 часа)
</p>
<p>
    После парсинга данные сохраняются в папке storage\app\public в excel-файлах формата Goszakupka-dd.mm.YYYY.xlsx
</p>
<p>
    Код парсера находится в файле app\Console\Commands\startParsing.php
</p>
<p>
    Для ручного запуска парсинга необходимо зайти в консоли в корневую директорию проекта и запустить команду php artisan app:start-parsing
</p>
<h2>Возможные проблемы</h2>
<p>
    При любых проблемах рекомендуется сначала изучить логи сервиса, которые находятся по адресу storage\logs\laravel.log
</p>
<p>
    Т.к. парсинг с сайтов icetrade.by и goszakupki.by ведется по html-коду, то при изменении дизайна этих сайтов данные с них не смогут быть получены. Для решения этой проблемы необходимо будет пересмотреть правила парсинга в файле app\Console\Commands\startParsing.php
</p>
<h2>Парсинг цен с магазина E-dostavka.by</h2>
<p>
    Добавлена команда для парсинга цен на товары с сайта e-dostavka.by. Парсинг осуществляется по коду товара в файле assort.xlsx. При успешном парсинге справа от кода записываются статус товара (0 - товар не найден, 1 - товар найден, есть в наличии, 2 - товар найден, нет в наличии) и цена товара на сайте, все изменения сохраняются в новом файле - actual.xlsx. Команда для запуска парсинга вручную - php artisan app:get-prices.
</p>