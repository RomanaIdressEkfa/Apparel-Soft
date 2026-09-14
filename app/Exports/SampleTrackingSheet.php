<?php

namespace App\Exports;

use App\Models\Task;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Rebuilds the merchandiser's "Sample Tracking List" workbook: same column order,
 * same header/row colouring, the two multi-line blocks, and embedded style photos.
 */
class SampleTrackingSheet
{
    private const HEADER_FILL = 'F4B183';   // peach header band

    private const ACTIVE_FILL = 'C6E0B4';   // green row

    private const HOLD_FILL = 'FFE699';     // amber — held by someone

    private const LICENSE_FILL = 'FFFF00';  // bright yellow — held on a licence issue

    private const DROP_FILL = 'FF0000';     // red (dropped) row

    private const BORDER = 'A6A6A6';

    /** @var array<int, array{label: string, width: float}> */
    private const COLUMNS = [
        ['label' => 'SL', 'width' => 5],
        ['label' => 'Season', 'width' => 9],
        ['label' => 'DEPT', 'width' => 8],
        ['label' => 'STYLE', 'width' => 26],
        ['label' => 'IMAGE', 'width' => 17],
        ['label' => 'Rcvd status', 'width' => 30],
        ['label' => 'Fab Art.', 'width' => 17],
        ['label' => 'Request Rcvd date', 'width' => 12],
        ['label' => 'Sample Type', 'width' => 12],
        ['label' => 'Techpack handover date', 'width' => 12],
        ['label' => 'Cutting status', 'width' => 11],
        ['label' => 'Sewing Status', 'width' => 11],
        ['label' => 'Wash send date', 'width' => 11],
        ['label' => 'Rcvd from Wash', 'width' => 11],
        ['label' => 'Sample submit date', 'width' => 11],
        ['label' => 'Price', 'width' => 10],
        ['label' => 'Booking Status', 'width' => 32],
        ['label' => 'Remarks', 'width' => 20],
    ];

    public function __construct(private readonly Collection $tasks) {}

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Sample Tracking');

        $this->writeHeader($sheet);

        $row = 2;
        $serial = 1;
        foreach ($this->tasks as $task) {
            $this->writeRow($sheet, $row, $task, $serial);
            $row++;
            $serial++;
        }

        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$this->lastColumn().max(1, $row - 1));

        return $book;
    }

    private function writeHeader(Worksheet $sheet): void
    {
        foreach (self::COLUMNS as $index => $column) {
            $letter = $this->letter($index);
            $sheet->setCellValue($letter.'1', $column['label']);
            $sheet->getColumnDimension($letter)->setWidth($column['width']);
        }

        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->getStyle('A1:'.$this->lastColumn().'1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
        ]);
    }

    private function writeRow(Worksheet $sheet, int $row, Task $task, int $serial): void
    {
        $dropped = $task->row_state === 'drop';
        $fill = match ($task->row_state) {
            'drop' => self::DROP_FILL,
            'hold' => self::HOLD_FILL,
            'license_hold' => self::LICENSE_FILL,
            default => self::ACTIVE_FILL,
        };
        $fontColour = match ($task->row_state) {
            'drop' => 'FFFFFF',
            'license_hold' => 'C00000',
            default => '000000',
        };
        $bold = in_array($task->row_state, ['drop', 'license_hold'], true);

        $values = [
            $serial,
            $task->season,
            $task->dept,
            $task->title,
            '', // image is placed as a drawing, not a value
            implode("\n", $task->rcvdStatusLines()),
            $task->fab_art,
            $this->date($task->received_at),
            $task->sample_type,
            $this->date($task->techpack_handover_date),
            $task->cutting_status,
            $task->sewing_status,
            $this->date($task->wash_send_date),
            $this->date($task->wash_rcvd_date),
            $this->date($task->sample_submit_date),
            $task->price_note,
            implode("\n", $task->bookingStatusLines()),
            $task->remarks,
        ];

        foreach ($values as $index => $value) {
            $sheet->setCellValueExplicit($this->letter($index).$row, (string) $value, DataType::TYPE_STRING);
        }

        $sheet->getRowDimension($row)->setRowHeight(86);

        $sheet->getStyle('A'.$row.':'.$this->lastColumn().$row)->applyFromArray([
            'font' => ['size' => 10, 'bold' => $bold, 'color' => ['rgb' => $fontColour]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $fill]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER]]],
        ]);

        // Booking Status and Remarks read better left-aligned, like the original.
        $sheet->getStyle('Q'.$row.':R'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $this->placeImage($sheet, $row, $task);
    }

    private function lastColumn(): string
    {
        return $this->letter(count(self::COLUMNS) - 1);
    }

    private function placeImage(Worksheet $sheet, int $row, Task $task): void
    {
        $image = $task->images->first();
        if ($image === null) {
            return;
        }

        $path = storage_path('app/public/task-images/'.$image->filename);
        if (! is_file($path)) {
            return;
        }

        $drawing = new Drawing;
        $drawing->setName('Style '.$task->id);
        $drawing->setDescription((string) $task->title);
        $drawing->setPath($path);
        $drawing->setHeight(104);
        $drawing->setOffsetX(10);
        $drawing->setOffsetY(6);
        $drawing->setCoordinates('E'.$row);
        $drawing->setWorksheet($sheet);
    }

    private function date(mixed $value): string
    {
        return $value ? $value->format('j-M') : '';
    }

    private function letter(int $index): string
    {
        return chr(65 + $index);
    }
}
