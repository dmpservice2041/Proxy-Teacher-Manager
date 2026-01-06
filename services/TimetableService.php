<?php
require_once __DIR__ . '/../models/Timetable.php';
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Settings.php';
require_once __DIR__ . '/../models/Classes.php';

class TimetableService {
    private $timetableModel;
    private $teacherModel;
    private $settingsModel;
    private $classModel;

    public function __construct() {
        $this->timetableModel = new Timetable();
        $this->teacherModel = new Teacher();
        $this->settingsModel = new Settings();
        $this->classModel = new Classes();
    }

    /**
     * Get all lectures that need a proxy for a given absent teacher on a specific date.
     */
    public function getAbsentLectures($teacherId, $date) {
        $dayOfWeek = date('N', strtotime($date)); // 1 (Mon) to 7 (Sun)
        return $this->timetableModel->getTeacherSchedule($teacherId, $dayOfWeek);
    }

    /**
     * Check if a teacher is free (not teaching) in a specific period.
     */
    public function isTeacherFree($teacherId, $date, $periodNo) {
        $dayOfWeek = date('N', strtotime($date));
        return !$this->timetableModel->isTeacherBusy($teacherId, $dayOfWeek, $periodNo);
    }

    /**
     * Get the total count of free periods a teacher has on a specific day.
     * Useful for load balancing logic.
     */
    public function getFreePeriodCount($teacherId, $date) {
        $dayOfWeek = date('N', strtotime($date));
        $totalPeriods = $this->settingsModel->get('total_periods', 8);
        return $this->timetableModel->countFreePeriods($teacherId, $dayOfWeek, $totalPeriods);
    }

    public function getClassDetails($classId) {
        return $this->classModel->find($classId);
    }
}
