<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\BeforeExport;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Files\LocalTemporaryFile;

class ExcelExport implements WithEvents
{
    use Exportable, RegistersEventListeners;

    protected $prodList;

    public function __construct($prodList)
    {
        $this->prodList = $prodList;
    }


    public function beforeExport(BeforeExport $event)
    {
        $prodList = $this->prodList;
        // get your template file
        $event->writer->reopen(new LocalTemporaryFile(storage_path('app/public/edostavka/assort.xlsx')), Excel::XLSX);
        $sheet = $event->getWriter()->getSheetByIndex(0);
        foreach ($prodList as $key => $val){
            $row = $key + 1;
            $sheet->getDelegate()->setCellValue('Q' . $row, $val['status']);
            $sheet->getDelegate()->setCellValue('R' . $row, $val['price']);
        }
        return $sheet;
    }
}