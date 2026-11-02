<?php

namespace Tests\Concerns;

use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Reads an Excel report download back into plain rows, one list per tab.
 */
trait ReadsXlsxReports
{
    /**
     * @return array{sheets: array<string, list<list<mixed>>>, formulas: bool}
     */
    protected function readXlsx(BinaryFileResponse $response): array
    {
        $path = $response->getFile()->getPathname();
        $reader = new Reader;
        $reader->open($path);
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];

            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        $zip = new ZipArchive;
        $zip->open($path);
        $formulas = false;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = (string) $zip->getNameIndex($index);

            if (str_starts_with($name, 'xl/worksheets/') && str_contains((string) $zip->getFromIndex($index), '<f>')) {
                $formulas = true;
            }
        }

        $zip->close();
        @unlink($path);

        return ['sheets' => $sheets, 'formulas' => $formulas];
    }

    /**
     * The value beside a label on the Summary tab.
     *
     * @param  array<string, list<list<mixed>>>  $sheets
     */
    protected function summaryValue(array $sheets, string $label): mixed
    {
        foreach ($sheets['Summary'] as $row) {
            foreach ([0, 3] as $column) {
                if (($row[$column] ?? null) === $label) {
                    return $row[$column + 1] ?? null;
                }
            }
        }

        $this->fail('Missing Summary line '.$label);
    }

    /**
     * The first row on a tab whose given column holds the text.
     *
     * @param  array<string, list<list<mixed>>>  $sheets
     * @return list<mixed>
     */
    protected function sheetRow(array $sheets, string $sheet, string $first, int $column = 0): array
    {
        foreach ($sheets[$sheet] as $row) {
            if (($row[$column] ?? null) === $first) {
                return $row;
            }
        }

        $this->fail('Missing row '.$first.' on '.$sheet);
    }

    /**
     * A column summed over a one-table tab, without its header or total.
     *
     * @param  array<string, list<list<mixed>>>  $sheets
     */
    protected function columnSum(array $sheets, string $sheet, int $column): float
    {
        $rows = array_slice($sheets[$sheet], 1);
        $rows = array_filter($rows, fn (array $row): bool => ! in_array('Total', array_slice($row, 0, 2), true));

        return (float) array_sum(array_map(fn (array $row): float => (float) ($row[$column] ?? 0), $rows));
    }

    /**
     * Every cell on every tab as one string, for "does not contain" checks.
     *
     * @param  array<string, list<list<mixed>>>  $sheets
     */
    protected function allText(array $sheets): string
    {
        $text = '';

        foreach ($sheets as $rows) {
            foreach ($rows as $row) {
                $text .= implode('|', array_map(fn (mixed $cell): string => is_scalar($cell) ? (string) $cell : '', $row))."\n";
            }
        }

        return $text;
    }
}
