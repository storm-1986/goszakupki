<?php

namespace App\Console\Commands;

use App\Exports\ParseExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class startParsing extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:start-parsing';

    /**
     * Для запуска парсинга выполнить команду:
     * php artisan app:start-parsing
     * 
     * @var string
     */
    protected $description = 'Запуск парсинга для сайта ';

    public function parse($url){
        $contents = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
        ])->withOptions([
            'verify' => false,
        ])->get($url)->getBody()->getContents();

        return $contents;
    }

    public function parseApi($url){
        $response = Http::withOptions([
            'verify' => false,
        ])->get($url)->json();

        return $response;
    }

    public function clear($str){
        $clearStr = str_replace(["\r", "\n", "\t", "<span class=\"nw\">", "</span> BYN", "<br />"], '', $str);
        return trim($clearStr);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Ссылки для парсинга 
        $urlIcetrade = 'https://icetrade.by/search/auctions?search_text=&zakup_type%5B1%5D=1&zakup_type%5B2%5D=1&auc_num=&okrb=10.5&company_title=&establishment=0&industries=&period=&created_from=&created_to=&request_end_from=&request_end_to=&r%5B1%5D=1&r%5B2%5D=2&r%5B7%5D=7&r%5B3%5D=3&r%5B4%5D=4&r%5B6%5D=6&r%5B5%5D=5&sort=num%3Adesc&sbm=1&onPage=100';

        $urlGoszakupki = 'https://goszakupki.by/tenders/posted?TendersSearch%5Bnum%5D=&TendersSearch%5BiceGiasNum%5D=&TendersSearch%5Btext%5D=&TendersSearch%5Bunp%5D=&TendersSearch%5Bcustomer_text%5D=&TendersSearch%5BunpParticipant%5D=&TendersSearch%5Bparticipant_text%5D=&TendersSearch%5Bprice_from%5D=&TendersSearch%5Bprice_to%5D=&TendersSearch%5Bcreated_from%5D=&TendersSearch%5Bcreated_to%5D=&TendersSearch%5Brequest_end_from%5D=&TendersSearch%5Brequest_end_to%5D=&TendersSearch%5Bauction_date_from%5D=&TendersSearch%5Bauction_date_to%5D=&TendersSearch%5Bindustry%5D=345%2C347%2C351%2C352&TendersSearch%5Btype%5D=&TendersSearch%5Bstatus%5D=&TendersSearch%5Bstatus%5D%5B%5D=Submission&TendersSearch%5Bregion%5D=&TendersSearch%5Bappeal%5D=&TendersSearch%5Bfunds%5D=';

        $urlGias = 'https://gias.by/search/api/v1/search/purchases';

        // Устанавливаем паузу между итерациями
        $pause = rand(1, 2);

        $icetradeContents = $this->parse($urlIcetrade);
        $goszakupkiContents = $this->parse($urlGoszakupki);

        // Получаем таблицу с данными
        $zakupkiIcetrade = preg_match('/<table class="auctions w100"  id="auctions-list" cellpadding="0" cellspacing="0">(.*)<\/table>/Uis',  $icetradeContents, $result);

        $zakupkiGoszakupki = preg_match('/<tbody>(.*)<\/tbody>/Uis',  $goszakupkiContents, $resultGoszakupki);
        $resultGoszakupki = $resultGoszakupki[1];

        // Парсим последнюю страницу пагинации чтобы узнать кол-во страниц
        $pagesGoszakupki = preg_match('/<li class="last">(.*)<\/li>/Uis',  $goszakupkiContents, $pages);
        if ($pagesGoszakupki == 1){
            $pages = strip_tags($pages[1]);
            for ($i = 2; $i <= $pages; $i++) { 
                // Устанавливаем паузу между итерациями
                sleep($pause);
                // Парсим предложения на каждой странице
                $urlGoszakupkiNext = $urlGoszakupki.'&page='.$i;
                $goszakupkiContents = $this->parse($urlGoszakupkiNext);
                preg_match('/<tbody>(.*)<\/tbody>/Uis',  $goszakupkiContents, $resultGoszakupkiNext);
                $resultGoszakupki .= $resultGoszakupkiNext[1];
            }
        }

        $exportData = array();

        // Парсинг для icetrade.by
        if ($zakupkiIcetrade == 1){
            // Получаем строки закупок
            $itemsIcetrade = preg_match_all('/<tr class=".+">(.*)<\/tr>/Uis', $result[1], $resItemsIcetrade);
            if (count($resItemsIcetrade[1]) > 0){
                foreach($resItemsIcetrade[1] as $key => $val){

                    // Получаем столбцы строк закупок
                    preg_match_all('/<td class=".+">(.*)<\/td>/Uis', $val, $resDataIcetrade);
                    foreach($resDataIcetrade[1] as $param => $data){
                        // Удаляем табы и переносы строк там, где они есть
                        if($param === 0 || $param == 4){
                            $exportData[$key][] = $this->clear($data);
                        }elseif($param != 2){
                            $exportData[$key][] = htmlspecialchars_decode($data);
                        }
                    }
                    // Устанавливаем паузу между итерациями
                    sleep($pause);
                    // Выделяем URL каждой закупки для парсинга подробной информации
                    preg_match('/href="(.*)"/Uis', $exportData[$key][0], $curZayavka);
                    $icetradeCurContents = $this->parse($curZayavka[1]);

                    // Парсим УНП
                    $getUNP = preg_match('/<tr class="af af-customer_data">.*<td class="afv">.*(\d{9}).*<\/td>/Uis', $icetradeCurContents, $UNP);
                    $exportData[$key][] = $UNP[1];

                    // Парсим дату размещения
                    $getDateR = preg_match('/<tr class="af af-created">.*<td class="afv">.*(\d{2}\.\d{2}\.\d{4}).*<\/td>/Uis', $icetradeCurContents, $dateR);
                    $exportData[$key][] = $dateR[1];

                    // Парсим адрес предприятия
                    $getAddr = preg_match('/<tr class="af af-customer_data">.*<td class="afv">(.*)\d{9}/Uis', $icetradeCurContents, $addr);
                    $exportData[$key][] = htmlspecialchars_decode($this->clear($addr[1]));

                    // Парсим состояние закупки
                    $getStatus = preg_match('/<tr id="lotRow1" class="af expanded">.*<td class="ac p81">.*\d.*<\/td>.*<td class="ac p81">(.+)<\/td>/Uis', $icetradeCurContents, $status);
                    $exportData[$key][] = $this->clear($status[1]);

                    // Парсим процедуру закупки
                    $getProc = preg_match('/<tr class="fst">.*<b>(.+)<\/b>/Uis', $icetradeCurContents, $proc);
                    $exportData[$key][] = $this->clear($proc[1]);
                }
            }
        }

        // Парсинг для goszakupki.by
        if ($zakupkiGoszakupki == 1){
            // Получаем строки закупок
            $itemsGoszakupki = preg_match_all('/<tr data-key="\d+">(.*)<\/tr>/Uis', $resultGoszakupki, $resItemsGoszakupki);
            if (count($resItemsGoszakupki[1]) > 0){
                foreach($resItemsGoszakupki[1] as $val){
                    // Проверка на наличие закупки в ГИАС
                    $check = strripos($val, '<small class="text-muted">в ГИАС:</small>');
                    if ($check === false){
                        // Количество элементов в итоговом массиве
                        $index = count($exportData);
                        // Получаем столбцы строк закупок
                        preg_match('/<td class="word-break">(.*)<\/td>/Uis', $val, $resDataGoszakupki);
                        $info = explode("<br><br>", $resDataGoszakupki[1]);
                        $opis = str_replace("/marketing", "https://goszakupki.by/marketing", $info[1]);
                        // Краткое описание предмета покупки
                        $exportData[$index][0] = $opis;
                        // Организатор
                        $exportData[$index][1] = $info[0];

                        // Номер
                        preg_match('/<td>auc(.*)</Uis', $val, $resDataGoszakupki);
                        $exportData[$index][2] = $resDataGoszakupki[1];

                        // Стоимость
                        preg_match('/\d{4}<\/td><td>(.*) BYN<\/td>/Uis', $val, $resDataGoszakupki);
                        $exportData[$index][3] = $resDataGoszakupki[1];

                        // Предложения до
                        preg_match('/<td>(\d{2}\.\d{2}\.\d{4})<\/td>/Uis', $val, $resDataGoszakupki);
                        $exportData[$index][4] = $resDataGoszakupki[1];
                        
                        // Состояние закупки
                        preg_match('/<span class="badge">(.*)<\/span>/Uis', $val, $resDataGoszakupki);
                        $exportData[$index][8] = $resDataGoszakupki[1];
                        
                        // Процедура закупки
                        preg_match('/<\/a><\/td><td>(.*)<\/td>/Uis', $val, $resDataGoszakupki);
                        $exportData[$index][9] = $resDataGoszakupki[1];

                        // Устанавливаем паузу между итерациями
                        sleep($pause);
                        // Выделяем URL каждой закупки для парсинга подробной информации
                        preg_match('/href="(.*)"/Uis', $exportData[$index][0], $curZayavka);
                        $goszakupkiCurContents = $this->parse($curZayavka[1]);

                        // Парсим УНП
                        $getUNP = preg_match('/<td>(\d{9})<\/td>/Uis', $goszakupkiCurContents, $UNP);
                        $exportData[$index][5] = $UNP[1];
                        
                        // Парсим дату размещения
                        $getDateR = preg_match('/<th class="col-md-4">Дата размещения приглашения<\/th>.*<td>(\d{2}\.\d{2}\.\d{4})<\/td>/Uis', $goszakupkiCurContents, $dateR);
                        $exportData[$index][6] = $dateR[1];
                        
                        // Парсим адрес предприятия
                        $getAddr = preg_match('/<th>Место нахождения организации<\/th>.*<td>(.*)<\/td>/Uis', $goszakupkiCurContents, $addr);
                        $exportData[$index][7] = $addr[1];

                        // Сортируем массив по ключу
                        ksort($exportData[$index]);
                    }
                }
            }
        }

        // Парсинг по API для gias.by
        $response = Http::withOptions([
            'verify' => false,
        ])->post($urlGias, [
            'page'          => 0,
            'pageSize'      => 200,
            'sumLotOkpbs'   => "10.5",
            'industry'      => [345, 347, 351, 352],
            'purchaseState' => [3],
        ])->json();
        
        foreach($response['content'] as $val){
            $index = count($exportData);
            $itemId = $val['purchaseGiasId'];
            $itemName = $val['title'];
            
            // Краткое описание предмета покупки
            $itemUrl = "<a href=\"https://gias.by/gias/#/purchase/current/$itemId\">$itemName</a>";
            $exportData[$index][0] = $itemUrl;
            // Организатор
            $exportData[$index][1] = $val['organizator']['name'];
            // Номер
            $exportData[$index][2] = $val['publicPurchaseNumber'];
            // Стоимость
            $exportData[$index][3] = $val['sumLot']['sumLot'];
            // Предложения до
            $beforeDate = is_null($val['requestDate']) ? '' : date('d.m.Y', $val['requestDate']/1000);
            $exportData[$index][4] = $beforeDate;
            // УНП
            $exportData[$index][5] = $val['organizator']['unp'];
            // Дата подачи
            $exportData[$index][6] = date('d.m.Y', $val['dtCreate']/1000);
            // Адрес
            $exportData[$index][7] = $val['organizator']['location'];
            // Состояние закупки
            $exportData[$index][8] = $val['stateName'];

            sleep($pause);
            $itemUrlApi = 'https://gias.by/purchase/api/v1/purchase/'.$itemId;
            $itemContent = $this->parseApi($itemUrlApi);
            // Процедура закупки
            $exportData[$index][9] = $itemContent['tenderFormName'];
        }

        // dd($exportData);

        // Добавляем заголовки
        array_unshift($exportData, ['Краткое описание предмета покупки', 'Организатор', 'Номер', 'Стоимость', 'Предложения до', 'УНП', 'Дата подачи', 'Адрес', 'Состояние закупки', 'Процедура закупки']);

        $currentDate = date('d.m.Y');

        // Экспорт в Excel
        $export = new ParseExport($exportData);
        // $file = storage_path('app/public/test_data.xlsx');
        Excel::store($export, 'public/Goszakupka-'.$currentDate.'.xlsx');
    }
}
