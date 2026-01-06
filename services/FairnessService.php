<?php
require_once __DIR__ . '/../models/Teacher.php';
require_once __DIR__ . '/../models/Section.php';

class FairnessService {
    private $teacherModel;
    private $sectionModel;
    
    // Memoize section priorities to avoid DB hits in loop
    private $sectionPriorities = [];

    public function __construct() {
        $this->teacherModel = new Teacher();
        $this->sectionModel = new Section();
        $this->loadSectionPriorities();
    }

    private function loadSectionPriorities() {
        $sections = $this->sectionModel->getAllOrderedByPriority();
        foreach ($sections as $sec) {
            $this->sectionPriorities[$sec['id']] = $sec['priority'];
        }
    }

    /**
     * The heart of the priority logic.
     * Sorts candidates based on the 8-step rule set.
     */
    public function sortCandidates($candidates, $absentTeacher, $subjectId, $date, $proxyCounts) {
        /*
         * $candidates: Array of teacher data (free and eligible)
         * $absentTeacher: Data of the teacher who is absent
         * $subjectId: Subject ID of the class to be covered
         * $date: Date of proxy
         * $proxyCounts = ['daily' => [...], 'weekly' => [...], 'free_periods' => [...]]
         */

        $absentTeacherSectionId = $absentTeacher['section_id'];
        $absentTeacherSubjects = $this->teacherModel->getSubjects($absentTeacher['id']);
        // Pluck IDs for easy comparison
        $absentSubjectIds = array_column($absentTeacherSubjects, 'id');

        usort($candidates, function($a, $b) use ($absentTeacherSectionId, $subjectId, $absentSubjectIds, $proxyCounts) {
            
            // 1. Same section as absent teacher
            $aSameSection = ($a['section_id'] == $absentTeacherSectionId);
            $bSameSection = ($b['section_id'] == $absentTeacherSectionId);
            if ($aSameSection !== $bSameSection) {
                return $bSameSection <=> $aSameSection; // True first
            }

            // 2 & 3. Section Priority (Higher priority = Lower integer value)
            $aPriority = $this->sectionPriorities[$a['section_id']] ?? 999;
            $bPriority = $this->sectionPriorities[$b['section_id']] ?? 999;
            if ($aPriority !== $bPriority) {
                return $aPriority <=> $bPriority; // Ascending (1 is better than 2)
            }

            // 4. Same subject teachers (Prefer teacher who teaches the subject of the lecture)
            // Note: The rule says "Same subject teachers". This usually means they teach the same subject generally, 
            // OR they teach the specific subject needed for this class. 
            // I'll assume it means "Teaches the subject required for this slot".
            $aSubjects = array_column($this->teacherModel->getSubjects($a['id']), 'id');
            $bSubjects = array_column($this->teacherModel->getSubjects($b['id']), 'id');
            
            $aHasSubject = in_array($subjectId, $aSubjects);
            $bHasSubject = in_array($subjectId, $bSubjects);

            if ($aHasSubject !== $bHasSubject) {
                return $bHasSubject <=> $aHasSubject; // True first
            }

            // 5. Teachers with more than 1 free period that day
            $aFreeTotal = $proxyCounts['free_periods'][$a['id']] ?? 0;
            $bFreeTotal = $proxyCounts['free_periods'][$b['id']] ?? 0;
            
            $aMoreThanOne = $aFreeTotal > 1;
            $bMoreThanOne = $bFreeTotal > 1;
            
            if ($aMoreThanOne !== $bMoreThanOne) {
                // If one has >1 and other doesn't, prefer the one with >1
                return $bMoreThanOne <=> $aMoreThanOne;
            }

            // 6. Teacher with highest total free periods that day
            if ($aFreeTotal !== $bFreeTotal) {
                return $bFreeTotal <=> $aFreeTotal; // Descending (more free is better)
            }

            // 7. Teacher with lowest proxy count today
            $aDailyCount = $proxyCounts['daily'][$a['id']] ?? 0;
            $bDailyCount = $proxyCounts['daily'][$b['id']] ?? 0;
            if ($aDailyCount !== $bDailyCount) {
                return $aDailyCount <=> $bDailyCount; // Ascending (less work is better)
            }

            // 8. Teacher with lowest proxy count this week
            $aWeeklyCount = $proxyCounts['weekly'][$a['id']] ?? 0;
            $bWeeklyCount = $proxyCounts['weekly'][$b['id']] ?? 0;
            if ($aWeeklyCount !== $bWeeklyCount) {
                return $aWeeklyCount <=> $bWeeklyCount; // Ascending
            }

            return 0; // Equal
        });

        return $candidates;
    }
}
