<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ParseExport implements FromCollection, WithColumnWidths, WithEvents
{
    /**
    * @return \Illuminate\Support\Collection
    */

    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    // Экспорт данных
    public function collection()
    {
        return collect($this->data);
    }

    // Задаем ширину ячеек
    public function columnWidths(): array
    {
        return [
            'A' => '60',
            'B' => '60',
            'C' => '20',
            'D' => '20',
            'E' => '15',
            'F' => '15',
            'G' => '20',
            'H' => '70',
            'I' => '30',
            'J' => '30',
        ];
    }

    // Переводим ссылки в формат excel
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                // Выделяем заголовки жирным
                $event->sheet->getStyle('A1:J1')->getFont()->setBold(true);

                $data = $this->data;
                $row = 2; // Начальная строка для добавления ссылок

                foreach ($data as $key => $val) {
                    if($key > 0){   // Пропускаем заголовки
                        preg_match('/href="(.*)"/Uis', $val[0], $link);
                        $url = $link[1];
                        $naim = strip_tags($val[0]);
                        $event->sheet->getDelegate()->setCellValue('A'.$row, $naim);
                        $event->sheet->getDelegate()->getCell('A'.$row)->getHyperlink()->setUrl($url);
                        $row++;
                    }
                }
            },
        ];
    }
}
