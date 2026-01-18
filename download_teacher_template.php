<?php
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=teacher_import_template.csv');

$output = fopen('php://output', 'w');

fputcsv($output, ['Name', 'EmpCode']);

fputcsv($output, ['John Doe', 'EMP001']);
fputcsv($output, ['Jane Smith', 'EMP002']);
fputcsv($output, ['Robert Brown', 'EMP003']);

fclose($output);
exit;
