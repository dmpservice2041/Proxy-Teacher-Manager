<?php
require_once 'config/app.php';
require_once 'models/Teacher.php';
require_once 'models/Classes.php';
require_once 'models/Timetable.php';
require_once 'models/Settings.php';

$teacherModel = new Teacher();
$classModel = new Classes();
$timetableModel = new Timetable();
$settingsModel = new Settings();

$mode = $_GET['mode'] ?? 'teacher'; // 'teacher' or 'class'
$items = [];
$title = '';
$filename = '';

if ($mode === 'class') {
    $items = $classModel->getAll();
    $title = 'All Classes Timetable';
    $filename = 'All_Classes_Timetable.pdf';
} else {
    $items = $teacherModel->getAllActive();
    $title = 'All Teachers Timetable';
    $filename = 'All_Teachers_Timetable.pdf';
}

$totalPeriods = $settingsModel->get('total_periods', 8);
$days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4F46E5;
            --primary-gradient: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
        }
        body {
            background: #fff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Reusing exact PDF styles from timetable.php */
        .slot-content {
            width: 100%;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
        }

        .slot-badge {
            background-color: #EEF2FF;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 12px;
            line-height: 1.2;
            border: 1px solid rgba(79, 70, 229, 0.1);
            display: inline-block;
            max-width: 100%;
            text-overflow: ellipsis;
            white-space: nowrap;
            overflow: hidden;
        }

        .slot-text {
            color: #374151;
            font-size: 0.85rem;
            font-weight: 500;
            line-height: 1.3;
        }
        
        .slot-subtext {
            color: #6B7280;
            font-size: 0.75rem;
            font-style: italic;
        }

        /* PDF Mode Overrides */
        .pdf-mode {
            background: white !important;
            box-shadow: none !important;
            padding: 0 !important;
        }
        .pdf-mode .fa-plus, 
        .pdf-mode .opacity-25 {
            display: none !important;
        }
        .pdf-mode table, 
        .pdf-mode th, 
        .pdf-mode td {
            border: 1px solid #000 !important;
            border-collapse: collapse !important;
        }
        .pdf-mode table {
            table-layout: fixed !important;
            width: 100% !important;
        }
        .pdf-mode th {
            font-size: 11px !important;
            padding: 5px !important;
            background-color: #f3f4f6 !important;
            color: #374151 !important;
            text-transform: uppercase;
        }
        .pdf-mode td {
            height: auto !important;
            vertical-align: middle !important;
        }
        .pdf-mode .slot-cell {
            border: none !important;
            min-height: 45px !important;
            padding: 4px !important;
        }
        .pdf-mode .slot-badge {
            font-size: 10px !important;
            padding: 2px 6px !important;
            margin-bottom: 2px !important;
            background-color: #EEF2FF !important;
            color: #4F46E5 !important;
            border: 1px solid #e0e7ff !important;
        }
        .pdf-mode .slot-text {
            font-size: 10px !important;
            line-height: 1.1 !important;
            color: #000 !important;
            white-space: normal !important;
            word-break: break-all !important; /* Force break */
            overflow-wrap: anywhere !important;
            max-width: 100% !important;
            display: block !important;
        }
        .pdf-mode .slot-subtext {
             font-size: 9px !important;
             color: #444 !important;
             display: block !important;
             white-space: normal !important;
             word-break: break-all !important; /* Force break */
             overflow-wrap: anywhere !important;
             max-width: 100% !important;
        }
        /* Allow badges to wrap in PDF too */
        .pdf-mode .slot-badge {
            white-space: normal !important;
            word-break: break-word !important;
            height: auto !important;
            max-width: 100% !important;
        }

        /* Layout Containers */
        .pdf-container {
            width: 100%;
            max-width: 100%;
            background: white;
        }
        
        .item-section {
            padding: 10px 20px;
            break-inside: avoid;
        }

        .item-header {
            font-size: 14px;
            font-weight: bold;
            color: #4F46E5;
            margin-bottom: 8px;
            border-left: 4px solid #4F46E5;
            padding-left: 8px;
            margin-top: 15px;
        }

        .html2pdf__page-break {
            height: 20px;
        }

        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body class="pdf-mode">

    <div class="no-print d-flex gap-2">
        <button onclick="generatePDF()" class="btn btn-primary shadow">
            <i class="fas fa-download me-2"></i> Download PDF
        </button>
        <button onclick="window.close()" class="btn btn-secondary shadow">
            Close
        </button>
    </div>

    <div id="pdfContent" class="pdf-container">
        <?php 
        $counter = 0;
        foreach ($items as $item): 
            $counter++;
            $itemId = $item['id'];
            $itemName = '';
            $schedule = [];
            
            if ($mode === 'class') {
                $itemName = $item['standard'] . '-' . $item['division'] . ' (' . ($item['section_name'] ?? '') . ')';
                for ($d = 1; $d <= 6; $d++) {
                    $schedule[$d] = $timetableModel->getClassSchedule($itemId, $d);
                }
            } else {
                $itemName = $item['name'];
                for ($d = 1; $d <= 6; $d++) {
                    $schedule[$d] = $timetableModel->getTeacherSchedule($itemId, $d);
                }
            }
        ?>
            
            <div class="item-section">
                <div class="item-header">
                    <?php echo htmlspecialchars($itemName); ?>
                </div>
                
                <table class="table mb-0 w-100">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 8%;">Day</th>
                            <?php for ($p=1; $p<=$totalPeriods; $p++): ?>
                                <th class="text-center" style="width: <?php echo 92 / $totalPeriods; ?>%;">P<?php echo $p; ?></th>
                            <?php endfor; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $dayNum => $dayName): ?>
                            <tr>
                                <th class="text-center align-middle"><?php echo substr($dayName, 0, 3); ?></th>
                                <?php for ($p=1; $p<=$totalPeriods; $p++): ?>
                                    <?php 
                                        $slots = [];
                                        foreach (($schedule[$dayNum] ?? []) as $s) {
                                            if ($s['period_no'] == $p) $slots[] = $s;
                                        }
                                    ?>
                                    <td class="p-0">
                                        <div class="slot-cell">
                                            <?php if (!empty($slots)): ?>
                                                <div class="slot-content">
                                                    <?php 
                                                    $firstSlot = $slots[0];
                                                    $isGroup = count($slots) > 1 || !empty($firstSlot['group_name']);
                                                    ?>
                                                    
                                                    <?php if ($mode === 'teacher'): ?>
                                                        <!-- Teacher View: Class (Badge) -> Subject (Text) -->
                                                        <div class="slot-badge"><?php echo $firstSlot['standard'] . '-' . $firstSlot['division']; ?></div>
                                                        <?php if ($isGroup): ?>
                                                            <div class="d-flex flex-column gap-1 w-100 mt-1">
                                                                <?php foreach ($slots as $slot): ?>
                                                                    <div class="slot-text border-top pt-1 text-muted" style="border-color: #eee !important;">
                                                                        <?php echo htmlspecialchars($slot['subject_name']); ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="slot-text"><?php echo htmlspecialchars($firstSlot['subject_name']); ?></div>
                                                        <?php endif; ?>
                                                        
                                                    <?php else: ?>
                                                        <!-- Class View: Subject (Badge) -> Teacher (Text) -->
                                                        <?php if ($isGroup): ?>
                                                            <div class="d-flex flex-column gap-1 w-100">
                                                                <?php foreach ($slots as $slot): ?>
                                                                    <div class="border-bottom pb-1 mb-1" style="border-color: #eee !important;">
                                                                        <div class="slot-badge mb-1"><?php echo htmlspecialchars($slot['subject_name']); ?></div>
                                                                        <div class="slot-subtext"><?php echo htmlspecialchars($slot['teacher_name']); ?></div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="slot-badge"><?php echo htmlspecialchars($firstSlot['subject_name']); ?></div>
                                                            <div class="slot-subtext"><?php echo htmlspecialchars($firstSlot['teacher_name']); ?></div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($counter % 2 == 0 && $counter < count($items)): ?>
                <div class="html2pdf__page-break"></div>
            <?php endif; ?>

        <?php endforeach; ?>
    </div>

    <!-- Html2Pdf -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script>
        function generatePDF() {
            const element = document.getElementById('pdfContent');
            const opt = {
                margin: [0.3, 0.3],
                filename: '<?php echo $filename; ?>',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
            };

            const btn = document.querySelector('button');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Generating...';
            btn.disabled = true;

            html2pdf().set(opt).from(element).save().then(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
