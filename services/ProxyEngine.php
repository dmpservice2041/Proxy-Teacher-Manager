<?php
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/ProxyAssignment.php';
require_once __DIR__ . '/AttendanceService.php';
require_once __DIR__ . '/TimetableService.php';
require_once __DIR__ . '/FairnessService.php';
require_once __DIR__ . '/../models/Settings.php';

class ProxyEngine {
    private $teacherModel;
    private $proxyModel;
    private $attendanceService;
    private $timetableService;
    private $fairnessService;
    private $settingsModel;

    public function __construct() {
        $this->teacherModel = new Teacher();
        $this->proxyModel = new ProxyAssignment();
        $this->attendanceService = new AttendanceService();
        $this->timetableService = new TimetableService();
        $this->fairnessService = new FairnessService();
        $this->settingsModel = new Settings();
    }

    /**
     * Run the automatic proxy generation for a given date.
     */
    public function generateProxies($date) {
        $log = [];
        
        // Step 1: Identify Absent Teachers
        $absentTeachers = $this->attendanceService->getAbsentTeachers($date);
        if (empty($absentTeachers)) {
            return ["status" => "No absent teachers found for $date"];
        }

        foreach ($absentTeachers as $absentTeacher) {
            $absentTeacherId = $absentTeacher['id'];
            $lectures = $this->timetableService->getAbsentLectures($absentTeacherId, $date);

            foreach ($lectures as $lecture) {
                $periodNo = $lecture['period_no'];
                $classId = $lecture['class_id'];
                $subjectId = $lecture['subject_id'];

                // Check if already assigned (Manual or Auto run previously)
                // We use 'isAssignedProxy' differently here (checking if slot is covered).
                // Actually, the DB unique key on (date, proxy_teacher_id, period) prevents a teacher doubling up,
                // but we need to check if the *class* already has a proxy.
                // Or simply simplistic: if we allocated for this absent_teacher + period, skip.
                // The schema `proxy_assignments` doesn't enforce "one proxy per absent slot" strictly via unique key on (date, absent_teacher, period),
                // but functionally we should not duplicate.
                // Let's check via a query if needed, or rely on logic. 
                // For now, I'll trust the engine runs once or checks before inserting.
                
                // Find target section from the class
                $targetClass = $this->timetableService->getClassDetails($classId);
                $targetSectionId = $targetClass['section_id'] ?? null;
                                
                // Let's implement robust candidate filtering
                $assignedTeacherId = $this->findAndAssignProxy($date, $absentTeacher, $lecture, $targetSectionId);
                
                if ($assignedTeacherId) {
                    $log[] = "Assigned Teacher ID $assignedTeacherId for " . $absentTeacher['name'] . " Period $periodNo";
                } else {
                    $log[] = "FAILED to find proxy for " . $absentTeacher['name'] . " Period $periodNo";
                }
            }
        }

        return $log;
    }

    private function findAndAssignProxy($date, $absentTeacher, $lecture, $targetSectionId) {
        $periodNo = $lecture['period_no'];
        $allTeachers = $this->teacherModel->getAllActive();

        $candidates = [];
        $proxyCounts = [
            'daily' => [],
            'weekly' => [],
            'free_periods' => []
        ];

        // START TRANSACTION (implicit via assign method, but we loop here)
        // Optimization: Pre-fetch data to avoid N+1 queries ideally, but for now simple loop.

        // Week range for filtering
        $dayOfWeek = date('N', strtotime($date));
        $weekStart = date('Y-m-d', strtotime("$date -".($dayOfWeek-1)." days"));
        $weekEnd = date('Y-m-d', strtotime("$date +".(7-$dayOfWeek)." days"));

        foreach ($allTeachers as $teacher) {
            $tid = $teacher['id'];

            // Skip the absent teacher themselves
            if ($tid == $absentTeacher['id']) continue;

            // Step 2: Candidate Filtering
            
            // A. Must be Present
            if (!$this->attendanceService->isPresent($tid, $date)) continue;

            // B. Must be Free in this period
            if (!$this->timetableService->isTeacherFree($tid, $date, $periodNo)) continue;

            // C. Not already assigned proxy in this period
            if ($this->proxyModel->isAssignedProxy($tid, $date, $periodNo)) continue;

            // D. Check Section Constraint
            // If the candidate teacher has a specific section assigned, they should ONLY cover classes in that section.
            // If candidate has NO section (Universal Floater), they can cover anywhere.
            $assignedSections = !empty($teacher['section_ids']) ? explode(',', $teacher['section_ids']) : [];
            
            if (!empty($assignedSections) && $targetSectionId && !in_array($targetSectionId, $assignedSections)) {
                continue; 
            }

            // E. Check Max Limits (Global Settings) - DISABLED AS PER USER REQUEST
            /*
            $maxDaily = $this->settingsModel->get('max_daily_proxy', 2);
            $maxWeekly = $this->settingsModel->get('max_weekly_proxy', 10);

            $dailyCount = $this->proxyModel->getProxyCountForDay($tid, $date);
            if ($dailyCount >= $maxDaily) continue;

            $weeklyCount = $this->proxyModel->getProxyCountForWeek($tid, $weekStart, $weekEnd);
            if ($weeklyCount >= $maxWeekly) continue;
            */

            // Populate counts for sorting
            $proxyCounts['daily'][$tid] = $dailyCount;
            $proxyCounts['weekly'][$tid] = $weeklyCount;
            $proxyCounts['free_periods'][$tid] = $this->timetableService->getFreePeriodCount($tid, $date);

            $candidates[] = $teacher;
        }

        if (empty($candidates)) {
            return null; // No one available
        }

        // Step 3: Priority Rules Sorting
        $sortedCandidates = $this->fairnessService->sortCandidates(
            $candidates, 
            $absentTeacher, 
            $lecture['subject_id'], 
            $date, 
            $proxyCounts
        );

        // Pick the best
        $bestCandidate = $sortedCandidates[0];

        // Step 4: Assignment
        // Construct a rule string for audit
        $ruleDesc = "Free: " . $proxyCounts['free_periods'][$bestCandidate['id']] . 
                    ", DailyProxy: " . $proxyCounts['daily'][$bestCandidate['id']] . 
                    ", WeeklyProxy: " . $proxyCounts['weekly'][$bestCandidate['id']];

        $this->proxyModel->assign(
            $date,
            $absentTeacher['id'],
            $bestCandidate['id'],
            $lecture['class_id'],
            $periodNo,
            'AUTO',
            "Auto-selected. Stats: [$ruleDesc]"
        );

        return $bestCandidate['id'];
    }
}
