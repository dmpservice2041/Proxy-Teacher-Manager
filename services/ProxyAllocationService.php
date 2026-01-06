<?php
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/ProxyAssignment.php';
require_once __DIR__ . '/../models/Settings.php';

class ProxyAllocationService {
    private $teacherModel;
    private $attendanceModel;
    private $timetableModel;
    private $proxyModel;
    private $settingsModel;

    public function __construct() {
        $this->teacherModel = new Teacher();
        $this->attendanceModel = new Attendance();
        $this->timetableModel = new Timetable();
        $this->proxyModel = new ProxyAssignment();
        $this->settingsModel = new Settings();
    }

    /**
     * Get all candidate teachers for a proxy slot who are present, free, and not already assigned.
     */
    public function getAvailableCandidates($date, $periodNo, $includeTeacherId = null) {
        $dayOfWeek = date('N', strtotime($date));
        $allTeachers = $this->teacherModel->getAllActive();
        $candidates = [];

        $totalPeriods = $this->settingsModel->get('total_periods', 8);

        foreach ($allTeachers as $teacher) {
            $tid = $teacher['id'];

            // 1. Must be Present
            if (!$this->attendanceModel->isPresent($tid, $date)) continue;

            // 2. Must be Free in this period
            if ($this->timetableModel->isTeacherBusy($tid, $dayOfWeek, $periodNo)) continue;

            // 3. Not already assigned a proxy in this period (unless it's the one we're explicitly including)
            if ($this->proxyModel->isAssignedProxy($tid, $date, $periodNo) && $tid != $includeTeacherId) continue;

            // 4. Calculate free periods
            $freePeriods = $this->timetableModel->countFreePeriods($tid, $dayOfWeek, $totalPeriods);

            $candidates[] = [
                'id' => $tid,
                'name' => $teacher['name'],
                'free_periods' => $freePeriods
            ];
        }

        // Sort candidates by free periods (descending) so teachers with more free time are at the top
        usort($candidates, function($a, $b) {
            return $b['free_periods'] <=> $a['free_periods'];
        });

        return $candidates;
    }

    /**
     * Get all absent slots for a date.
     */
    public function getAbsentSlots($date) {
        $absentTeachers = $this->attendanceModel->getAbsentTeachers($date);
        $dayOfWeek = date('N', strtotime($date));
        $slots = [];
        
        $existingAssignments = $this->proxyModel->getAssignmentsForDate($date);

        foreach ($absentTeachers as $teacher) {
            $schedule = $this->timetableModel->getTeacherSchedule($teacher['id'], $dayOfWeek);
            foreach ($schedule as $lecture) {
                $slotKey = $teacher['id'] . '|' . $lecture['period_no'] . '|' . $lecture['class_id'];
                $slots[] = [
                    'teacher_id' => $teacher['id'],
                    'teacher_name' => $teacher['name'],
                    'period_no' => $lecture['period_no'],
                    'class_id' => $lecture['class_id'],
                    'standard' => $lecture['standard'],
                    'division' => $lecture['division'],
                    'subject_id' => $lecture['subject_id'],
                    'subject_name' => $lecture['subject_name'],
                    'group_name' => $lecture['group_name'] ?? null,
                    'assigned_proxy_id' => $existingAssignments[$slotKey] ?? null
                ];
            }
        }

        return $slots;
    }
}
