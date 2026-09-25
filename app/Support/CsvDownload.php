<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvDownload
{
    /**
     * UTF-8 with BOM so Excel keeps peso signs and Filipino text.
     *
     * @param  callable(resource): void  $write
     */
    public function stream(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            $write($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Neutralise spreadsheet formula injection. Excel treats a leading
     * =, +, -, @, tab or CR as a formula. A leading quote makes it text.
     * Numbers stay numbers so money columns remain usable.
     *
     * @param  list<mixed>  $cells
     * @return list<mixed>
     */
    public function row(array $cells): array
    {
        return array_map(function (mixed $cell): mixed {
            if (! is_string($cell) || $cell === '') {
                return $cell;
            }

            $first = $cell[0];

            if (in_array($first, ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$cell;
            }

            return $cell;
        }, array_values($cells));
    }
}
