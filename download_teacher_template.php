<?php
// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=teacher_import_template.csv');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the column headings
fputcsv($output, ['Name', 'EmpCode']);

// Example data rows
fputcsv($output, ['John Doe', 'EMP001']);
fputcsv($output, ['Jane Smith', 'EMP002']);
fputcsv($output, ['Robert Brown', 'EMP003']);

fclose($output);
exit;
