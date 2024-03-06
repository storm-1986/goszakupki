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
     * The console command description.
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

        $icetradeContents = $this->parse($urlIcetrade);

        // Получаем таблицу с данными
        $zakupkiIcetrade = preg_match('/<table class="auctions w100"  id="auctions-list" cellpadding="0" cellspacing="0">(.*)<\/table>/Uis',  $icetradeContents, $result);

        $icetrade = array();
        
        if ($zakupkiIcetrade){
            // Получаем строки закупок
            $itemsIcetrade = preg_match_all('/<tr class=".+">(.*)<\/tr>/Uis', $result[1], $resItemsIcetrade);
            if (count($resItemsIcetrade[1]) > 0){
                foreach($resItemsIcetrade[1] as $key => $val){

                    // Получаем столбцы строк закупок
                    preg_match_all('/<td class=".+">(.*)<\/td>/Uis', $val, $resDataIcetrade);
                    foreach($resDataIcetrade[1] as $param => $data){
                        // Удаляем табы и переносы строк там, где они есть
                        if($param === 0 || $param == 4){
                            $icetrade[$key][] =  $this->clear($data);
                        }elseif($param != 2){
                            $icetrade[$key][] = htmlspecialchars_decode($data);
                        }
                    }
                    // Устанавливаем паузу между итерациями
                    $pause = rand(1, 3);
                    sleep($pause);
                    // Выделяем URL каждой закупки для парсинга подробной информации
                    preg_match('/href="(.*)"/Uis', $icetrade[$key][0], $curZayavka);
                    $icetradeCurContents = $this->parse($curZayavka[1]);

                    // Парсим УНП
                    $getUNP = preg_match('/<tr class="af af-customer_data">.*<td class="afv">.*(\d{9}).*<\/td>/Uis',$icetradeCurContents, $UNP);
                    // $this->info($UNP[1]);
                    $icetrade[$key][] = $UNP[1];

                    // Парсим дату размещения
                    $getDateR = preg_match('/<tr class="af af-created">.*<td class="afv">.*(\d{2}\.\d{2}\.\d{4}).*<\/td>/Uis', $icetradeCurContents, $dateR);
                    // $this->info($dateR[1]);
                    $icetrade[$key][] = $dateR[1];

                    // Парсим адрес предприятия
                    $getAddr = preg_match('/<tr class="af af-customer_data">.*<td class="afv">(.*)\d{9}/Uis', $icetradeCurContents, $addr);
                    // $this->info($this->clear($addr[1]));
                    $icetrade[$key][] = htmlspecialchars_decode($this->clear($addr[1]));

                    // Парсим состояние закупки
                    $getStatus = preg_match('/<tr id="lotRow1" class="af expanded">.*<td class="ac p81">.*\d.*<\/td>.*<td class="ac p81">(.+)<\/td>/Uis', $icetradeCurContents, $status);
                    // $this->info($this->clear($status[1]));
                    $icetrade[$key][] = $this->clear($status[1]);

                    // Парсим процедуру закупки
                    $getProc = preg_match('/<tr class="fst">.*<b>(.+)<\/b>/Uis', $icetradeCurContents, $proc);
                    // $this->info($this->clear($proc[1]));
                    $icetrade[$key][] = $this->clear($proc[1]);
                }
            }
        }
        // Добавляем заголовки
        array_unshift($icetrade, ['Краткое описание предмета покупки', 'Организатор', 'Номер', 'Стоимость', 'Предложения до', 'УНП', 'Дата подачи', 'Адрес', 'Состояние закупки', 'Процедура закупки']);
        // dd($icetrade);

        $currentDate = date('d.m.Y');

        // Экспорт в Excel
        $export = new ParseExport($icetrade);
        // $file = storage_path('app/public/test_data.xlsx');
        Excel::store($export, 'public/Goszakupka-'.$currentDate.'.xlsx');
    }
}
