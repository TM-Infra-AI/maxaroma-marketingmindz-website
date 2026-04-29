<?php

namespace App\Exports;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;

class ExportOrders implements FromArray, WithHeadings, WithEvents
{
	use Exportable;

    private $myArray;
    private $myHeadings;
    private $sheetflag = '';

   public function __construct($myArray, $myHeadings, $sheetflag){
        $this->myArray = $myArray;
        $this->myHeadings = $myHeadings;
		$this->sheetflag = $sheetflag;
    }

    public function array(): array{
        return $this->myArray;
    }

    public function headings(): array{
        return $this->myHeadings;
    }
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
				if($this->sheetflag == 'SpecialProducts')
				{ 
					foreach ($event->sheet->getColumnIterator('H') as $row) 
					{	
						if($row->getColumnIndex() == 'H')
						{
							foreach ($row->getCellIterator() as $key => $cell) 
							{
								if($key > 1)
								{
									if(file_exists(config('global.PRD_LARGE_IMG_PATH') . $cell->getValue().".jpg")) {
										$mainImageUrl = config('global.PRD_LARGE_IMG_URL').$cell->getValue().".jpg";
									}
									else if(file_exists(config('global.PRD_LARGE_IMG_PATH') . $cell->getValue().".JPG")) {
										$mainImageUrl = config('global.PRD_LARGE_IMG_URL').$cell->getValue().".JPG";
									}
									else if(file_exists(config('global.PRD_LARGE_IMG_PATH') . $cell->getValue().".JPEG")) {
										$mainImageUrl = config('global.PRD_LARGE_IMG_URL').$cell->getValue().".JPEG";
									}
									else if(file_exists(config('global.PRD_LARGE_IMG_PATH') . $cell->getValue().".jpeg")) {
										$mainImageUrl = config('global.PRD_LARGE_IMG_URL').$cell->getValue().".jpeg";
									}
									 else {
										$mainImageUrl = config('global.NO_IMAGE_LARGE');
									}
									$cell->getHyperlink()->setUrl($mainImageUrl);
									$event->sheet->getStyle($cell->getCoordinate())->applyFromArray([
										'font' => [
											'color' => ['rgb' => '0000FF'],
											'underline' => 'single'
										]
									]);
								}
							}
						}
					}
				}
            },
        ];
    }
}

?>
