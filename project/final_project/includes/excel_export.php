<?php
declare(strict_types=1);

function excel_export_headers(string $filename): void
{
    header('Pragma: public');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Content-type: application/vnd.ms-excel');
    header('Content-Disposition: inline; filename="' . $filename . '";');
    header('Content-Transfer-Encoding: binary');
}

function excel_export_begin(string $filename): void
{
    excel_export_headers($filename);
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
}
