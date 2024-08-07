<?php

namespace App\Console\Commands;

use App\Exports\ExcelExport;
use App\Imports\AssortImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class GetPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:get-prices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Парсинг цен для продукции с магазина edostavka.by';

    public function parse($url){
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36',
        ])->withOptions([
            'verify' => false,
        ])->get($url);
        $status = $response->status();
        $code = $response->getBody()->getContents();

        return [$status, $code];
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $assort = Excel::toArray(new AssortImport, storage_path('app/public/edostavka/assort.xlsx'));
        $prodList = [];
        $pause = rand(1, 3);
        $bar = $this->output->createProgressBar(count($assort[0]));
        $bar->start();
        foreach ($assort[0] as $key => $val) {
            if ($key >= 9 && $val[8] > 0){
                $url = 'https://edostavka.by/product/'.$val[8];
                try{
                    $content = $this->parse($url);
                }catch (\Exception $e) {
                    $message = 'Ошибка открытия страницы ' . $url;
                    $this->error($message);
                    Log::error($message);
                }
                if (isset($content)){
                    if ($content[0] == 404) {
                        // Товара нет на сайте
                        $prodList[$key]['status'] = 0;
                        $prodList[$key]['price'] = '';
                        $prodList[$key]['code'] = $val[8];
                        sleep($pause);
                    }elseif ($content[0] == 200) {
                        $price = preg_match('/"price": "(.+)"/Uis', $content[1], $resPrice);
                        if (!$price) {
                            $status = 2;
                            $price = '';
                        }else{
                            $status = 1;
                            $price = $resPrice[1];
                        }
                        $prodList[$key]['status'] = $status;
                        $prodList[$key]['price'] = $price;
                        $prodList[$key]['code'] = $val[8];
                        sleep($pause);
                    }
                }
            }
            $bar->advance();
        }
        $bar->finish();
        Excel::store(new ExcelExport($prodList), 'public/edostavka/actual.xlsx');
    }
}