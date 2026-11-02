<?php

namespace App\Support\Spreadsheet;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Border;
use OpenSpout\Common\Entity\Style\BorderPart;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AutoFilter;
use OpenSpout\Writer\Common\Entity\Sheet;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Options\PageOrientation;
use OpenSpout\Writer\XLSX\Options\PageSetup;
use OpenSpout\Writer\XLSX\Options\PaperSize;
use OpenSpout\Writer\XLSX\Writer;

/**
 * A styled Excel report: a Summary tab with two columns of key numbers, then
 * one tab per table. Text is always written as text, so a crop named "=1+1"
 * can never run as a formula.
 */
final class XlsxReport
{
    public const PESO = '"₱"#,##0.00';

    public const PERCENT = '0.0"%"';

    public const COUNT = '#,##0';

    public const QUANTITY = '#,##0.##;-#,##0.##;0';

    private const GREEN = '1F5A3E';

    private const LIGHT_GREEN = 'E8F1EC';

    private const ZEBRA = 'FAFAF7';

    private const MUTED = '555555';

    private const NOTE = '7A4B00';

    private const LINE = 'DADCD8';

    private Writer $writer;

    private Options $options;

    private bool $first = true;

    private int $rows = 0;

    public function __construct(private string $path)
    {
        $this->options = new Options;
        $this->options->DEFAULT_ROW_STYLE = (new Style)->setFontName('Calibri')->setFontSize(11);
        // Printed landscape on A4, every column on one page width.
        $this->options->setPageSetup(new PageSetup(PageOrientation::LANDSCAPE, PaperSize::A4, 0, 1));
        $this->writer = new Writer($this->options);
        $this->writer->openToFile($path);
    }

    /**
     * Data rows written so far, for the export log.
     */
    public function rowCount(): int
    {
        return $this->rows;
    }

    /**
     * The first tab: a title, a line of context, then two side-by-side
     * blocks of label and value.
     *
     * @param  array{0: string, 1: list<array{0: string, 1: mixed, 2?: ?string}>}  $left
     * @param  array{0: string, 1: list<array{0: string, 1: mixed, 2?: ?string}>}  $right
     */
    public function summary(string $sheetName, string $title, string $context, array $left, array $right, ?string $note = null): void
    {
        $sheet = $this->sheet($sheetName);
        $sheet->setColumnWidth(30, 1, 4);
        $sheet->setColumnWidth(18, 2, 5);
        $sheet->setColumnWidth(3, 3);
        $sheet->setSheetView((new SheetView)->setShowGridLines(false));
        $index = $sheet->getIndex();

        $this->writer->addRow(new Row([$this->text($title, $this->titleStyle()), ...$this->blanks(4, $this->titleStyle())], null));
        $this->options->mergeCells(0, 1, 4, 1, $index);
        $this->writer->addRow(new Row([$this->text($context, (new Style)->setFontItalic()->setFontColor(self::MUTED))], null));
        $this->options->mergeCells(0, 2, 4, 2, $index);
        $this->writer->addRow(Row::fromValues([]));

        $section = $this->sectionStyle();
        $this->writer->addRow(new Row([
            $this->text($left[0], $section),
            $this->blank($section),
            $this->blank(),
            $this->text($right[0], $section),
            $this->blank($section),
        ], null));
        $this->options->mergeCells(0, 4, 1, 4, $index);
        $this->options->mergeCells(3, 4, 4, 4, $index);

        $count = max(count($left[1]), count($right[1]));

        for ($line = 0; $line < $count; $line++) {
            $this->writer->addRow(new Row([
                ...$this->pair($left[1][$line] ?? null),
                $this->blank(),
                ...$this->pair($right[1][$line] ?? null),
            ], null));
            $this->rows++;
        }

        if ($note !== null) {
            $this->writer->addRow(Row::fromValues([]));
            $this->writer->addRow(new Row([$this->text($note, (new Style)->setFontItalic()->setFontColor(self::MUTED))], null));
            $this->options->mergeCells(0, $count + 6, 4, $count + 6, $index);
        }
    }

    /**
     * A tab holding one table: a frozen header row with filters, striped
     * rows and an optional bold total row.
     *
     * @param  list<array{0: string, 1: float, 2?: ?string, 3?: bool}>  $columns  header, width, number format, wrap
     * @param  list<list<mixed>>  $rows
     * @param  list<mixed>|null  $total
     */
    public function table(string $sheetName, array $columns, array $rows, ?array $total = null): void
    {
        $sheet = $this->sheet($sheetName);
        $this->widths($sheet, $columns);
        $sheet->setSheetView((new SheetView)->setFreezeRow(2));

        $this->header($columns);
        $this->body($columns, $rows);

        if ($rows !== []) {
            $sheet->setAutoFilter(new AutoFilter(0, 1, count($columns) - 1, count($rows) + 1));
        }

        if ($total !== null) {
            $this->totalRow($columns, $total);
        }
    }

    /**
     * A tab holding several small tables, one under the other, each with a
     * green title.
     *
     * @param  list<array{0: string, 1: float, 2?: ?string, 3?: bool}>  $columns
     * @param  list<array{0: string, 1: list<list<mixed>>}>  $tables  title and rows
     */
    public function tables(string $sheetName, array $columns, array $tables): void
    {
        $sheet = $this->sheet($sheetName);
        $this->widths($sheet, $columns);
        $sheet->setSheetView((new SheetView)->setShowGridLines(false));
        $line = 0;

        foreach ($tables as $position => [$title, $rows]) {
            if ($position > 0) {
                $this->writer->addRow(Row::fromValues([]));
                $line++;
            }

            $this->writer->addRow(new Row([$this->text($title, $this->sectionStyle()), ...$this->blanks(count($columns) - 1, $this->sectionStyle())], null));
            $line++;
            $this->options->mergeCells(0, $line, count($columns) - 1, $line, $sheet->getIndex());
            $this->header($columns);
            $this->body($columns, $rows);
            $line += 1 + count($rows);
        }
    }

    public function close(): void
    {
        $this->writer->close();
    }

    private function sheet(string $name): Sheet
    {
        $sheet = $this->first ? $this->writer->getCurrentSheet() : $this->writer->addNewSheetAndMakeItCurrent();
        $this->first = false;
        $sheet->setName($name);

        return $sheet;
    }

    /**
     * @param  list<array{0: string, 1: float, 2?: ?string, 3?: bool}>  $columns
     */
    private function widths(Sheet $sheet, array $columns): void
    {
        foreach ($columns as $index => $column) {
            $sheet->setColumnWidth($column[1], $index + 1);
        }
    }

    /**
     * @param  list<array{0: string, 1: float, 2?: ?string, 3?: bool}>  $columns
     */
    private function header(array $columns): void
    {
        $style = (new Style)
            ->setFontBold()
            ->setFontColor('FFFFFF')
            ->setBackgroundColor(self::GREEN)
            ->setCellAlignment(CellAlignment::CENTER)
            ->setCellVerticalAlignment(CellVerticalAlignment::CENTER)
            ->setShouldWrapText();

        $this->writer->addRow(new Row(array_map(
            fn (array $column): Cell => $this->text($column[0], $style),
            $columns,
        ), null));
    }

    /**
     * @param  list<array{0: string, 1: float, 2?: ?string, 3?: bool}>  $columns
     * @param  list<list<mixed>>  $rows
     */
    private function body(array $columns, array $rows): void
    {
        foreach ($rows as $number => $row) {
            $cells = [];

            foreach ($columns as $index => $column) {
                $style = (new Style)
                    ->setCellVerticalAlignment(CellVerticalAlignment::TOP)
                    ->setBorder($this->hairline());

                if ($number % 2 === 1) {
                    $style->setBackgroundColor(self::ZEBRA);
                }

                if ($index === $this->labelColumn($columns)) {
                    $style->setFontBold();
                }

                if ($index === 0 && is_int($row[$index] ?? null)) {
                    $style->setCellAlignment(CellAlignment::CENTER);
                }

                if (($column[3] ?? false) === true) {
                    $style->setShouldWrapText()->setFontColor(self::NOTE);
                }

                $cells[] = $this->cell($row[$index] ?? null, $style, $column[2] ?? null);
            }

            $this->writer->addRow(new Row($cells, null));
            $this->rows++;
        }
    }

    /**
     * @param  list<array{0: string, 1: float, 2?: ?string, 3?: bool}>  $columns
     * @param  list<mixed>  $total
     */
    private function totalRow(array $columns, array $total): void
    {
        $cells = [];

        foreach ($columns as $index => $column) {
            $style = (new Style)
                ->setFontBold()
                ->setBorder(new Border(new BorderPart(Border::TOP, self::GREEN, Border::WIDTH_MEDIUM, Border::STYLE_SOLID)));
            $cells[] = $this->cell($total[$index] ?? null, $style, $column[2] ?? null);
        }

        $this->writer->addRow(new Row($cells, null));
    }

    /**
     * The first text column is the row's label and is set in bold.
     *
     * @param  list<array{0: string, 1: float, 2?: ?string, 3?: bool}>  $columns
     */
    private function labelColumn(array $columns): int
    {
        foreach ($columns as $index => $column) {
            if (($column[2] ?? null) === null) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * @param  array{0: string, 1: mixed, 2?: ?string}|null  $pair
     * @return list<Cell>
     */
    private function pair(?array $pair): array
    {
        if ($pair === null) {
            return [$this->blank(), $this->blank()];
        }

        $line = $this->hairline();
        $value = (new Style)->setFontBold()->setCellAlignment(CellAlignment::RIGHT)->setBorder($line);

        return [
            $this->text($pair[0], (new Style)->setBorder($line)),
            $this->cell($pair[1], $value, $pair[2] ?? null),
        ];
    }

    private function cell(mixed $value, Style $style, ?string $format): Cell
    {
        if ($value === null || $value === '') {
            return new EmptyCell(null, $style);
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value) && $format !== null)) {
            if ($format !== null) {
                $style->setFormat($format);
            }

            return new NumericCell(is_string($value) ? (float) $value : $value, $style);
        }

        if ($format !== null) {
            $style->setCellAlignment(CellAlignment::RIGHT);
        }

        return $this->text((string) $value, $style);
    }

    private function text(string $value, ?Style $style = null): StringCell
    {
        return new StringCell($value, $style);
    }

    private function blank(?Style $style = null): EmptyCell
    {
        return new EmptyCell(null, $style);
    }

    /**
     * @return list<EmptyCell>
     */
    private function blanks(int $count, Style $style): array
    {
        return array_map(fn (): EmptyCell => $this->blank($style), range(1, max(1, $count)));
    }

    private function titleStyle(): Style
    {
        return (new Style)->setFontBold()->setFontSize(14)->setFontColor('FFFFFF')->setBackgroundColor(self::GREEN);
    }

    private function sectionStyle(): Style
    {
        return (new Style)
            ->setFontBold()
            ->setFontSize(12)
            ->setFontColor(self::GREEN)
            ->setBackgroundColor(self::LIGHT_GREEN)
            ->setBorder(new Border(new BorderPart(Border::BOTTOM, self::GREEN, Border::WIDTH_MEDIUM, Border::STYLE_SOLID)));
    }

    private function hairline(): Border
    {
        return new Border(new BorderPart(Border::BOTTOM, self::LINE, Border::WIDTH_THIN, Border::STYLE_SOLID));
    }
}
