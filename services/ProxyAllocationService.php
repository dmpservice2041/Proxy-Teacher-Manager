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
    private $blockedPeriodModel;
    private $dailyOverridesModel;
    private $settingsModel;
    private $pdo; // Added PDO property

    public function __construct() {
        $this->teacherModel = new Teacher();
        $this->attendanceModel = new Attendance();
        $this->timetableModel = new Timetable();
        $this->proxyModel = new ProxyAssignment();
        $this->settingsModel = new Settings();
        
        $this->pdo = Database::getInstance()->getConnection(); // Initialize PDO

        require_once __DIR__ . '/../models/BlockedPeriod.php';
        $this->blockedPeriodModel = new BlockedPeriod();
        
        require_once __DIR__ . '/../models/DailyOverrides.php';
        $this->dailyOverridesModel = new DailyOverrides();
    }
    
    // ... existing code ...



    /**
     * Get all candidate teachers for a proxy slot who are present, free, and not already assigned.
     */
    public function getAvailableCandidates($date, $periodNo, $includeTeacherId = null) {
        $dayOfWeek = date('N', strtotime($date));
        $dayName = date('l', strtotime($date)); // e.g., Saturday

        // 0. Check if this period is globally blocked for everyone
        if ($this->blockedPeriodModel->isBlocked($dayName, $periodNo)) {
            return [];
        }

        $allTeachers = $this->teacherModel->getAllActive();
        $candidates = [];

        $totalPeriods = $this->settingsModel->get('total_periods', 8);
        
        // Fetch all blocked periods
        $allBlocked = $this->blockedPeriodModel->getBlockedPeriods();
        $blockedToday = $allBlocked[$dayName] ?? [];
        
        // Fetch Daily Overrides
        // Map: ['TEACHER_DUTY' => [TID => [Period => true]], 'CLASS_ABSENT' => [CID => [Period => true]]]
        $overridesMap = $this->dailyOverridesModel->getOverridesMap($date);
        $teacherDutyMap = $overridesMap['TEACHER_DUTY'] ?? [];
        $classAbsentMap = $overridesMap['CLASS_ABSENT'] ?? [];

        foreach ($allTeachers as $teacher) {
            $tid = $teacher['id'];

            // 1. Must be Present
            if (!$this->attendanceModel->isPresent($tid, $date)) continue;
            
            // 2. DAILY OVERRIDE: Check if Teacher is on Duty (Busy) for this period
            if (isset($teacherDutyMap[$tid])) {
                // Check specific period OR All Day (key 0)
                if (isset($teacherDutyMap[$tid][$periodNo]) || isset($teacherDutyMap[$tid][0])) {
                    continue; // Busy with Duty
                }
            }

            // 3. Check Timetable Schedule (Busy or Free?)
            $schedule = $this->timetableModel->getTeacherSchedule($tid, $dayOfWeek);
            $busyPeriods = []; 
            // Build map of Period -> ClassID to handle Class Absence logic
            foreach ($schedule as $s) {
                $busyPeriods[$s['period_no']] = $s['class_id'];
            }
            
            // Is busy in requested period?
            if (isset($busyPeriods[$periodNo])) {
                // Exception: Is that class Marked Absent?
                $cid = $busyPeriods[$periodNo];
                $isClassAbsent = isset($classAbsentMap[$cid]) && (isset($classAbsentMap[$cid][$periodNo]) || isset($classAbsentMap[$cid][0]));
                
                if (!$isClassAbsent) {
                     continue; // Truly Busy
                }
                // If Class Absent, we proceed (Teacher is Free!)
            }
            
            // --- Teacher is a candidate! Calculate Free Periods Count ---

            // Get teacher's classes for validity check
            $teacherClasses = $this->timetableModel->getTeacherClasses($tid);
            
            $freePeriods = 0;
            // Iterate all possible periods for the day
            for ($p = 1; $p <= $totalPeriods; $p++) {
                // If period is GLOBALLY blocked, it doesn't count.
                if (isset($blockedToday[$p]['global'])) continue;
                
                // If teacher is ON DUTY for period P -> Busy
                if (isset($teacherDutyMap[$tid]) && (isset($teacherDutyMap[$tid][$p]) || isset($teacherDutyMap[$tid][0]))) continue;
                
                // If teacher is BUSY in Timetable
                if (isset($busyPeriods[$p])) {
                     // Check if Class Absent exception applies
                     $cid = $busyPeriods[$p];
                     $isClassAbsent = isset($classAbsentMap[$cid]) && (isset($classAbsentMap[$cid][$p]) || isset($classAbsentMap[$cid][0]));
                     if (!$isClassAbsent) {
                         continue; // Truly Busy
                     }
                }
                
                // CHECK VALIDITY: Is the teacher supposed to be in school?
                $isByDefaultAvailable = empty($teacherClasses);
                $hasOpenClass = false;
                
                if (!$isByDefaultAvailable) {
                    foreach ($teacherClasses as $cid) {
                        // Check BlockedPeriod (Weekly)
                        if (!isset($blockedToday[$p][$cid])) {
                             $hasOpenClass = true;
                             break;
                        }
                    }
                }
                
                if ($isByDefaultAvailable || $hasOpenClass) {
                     $freePeriods++;
                }
            }
            
            $candidates[] = [
                'id' => $tid,
                'name' => $teacher['name'],
                'free_periods' => $freePeriods
            ];
        }

        // Sort candidates
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
        $dayName = date('l', strtotime($date)); // e.g., Saturday

        $slots = [];
        
        $existingAssignments = $this->proxyModel->getAssignmentsForDate($date);

        // Pre-fetch all blocked periods to optimize loop
        // Structure: [Day][Period][ClassID/Global]
        // But getBlockedPeriods returns [Day][Period][Entity] (Global/ClassID) => true
        // Actually my getBlockedPeriods returns [day][period][id] = true
        $allBlocked = $this->blockedPeriodModel->getBlockedPeriods(); 
        $blockedToday = $allBlocked[$dayName] ?? [];

        foreach ($absentTeachers as $teacher) {
            $schedule = $this->timetableModel->getTeacherSchedule($teacher['id'], $dayOfWeek);
            foreach ($schedule as $lecture) {
                
                // CHECK BLOCKS:
                // 1. Is Period Globally Blocked?
                if (isset($blockedToday[$lecture['period_no']]['global'])) {
                    continue; 
                }
                // 1.5 Is Class marked ABSENT/CLOSED for today (Daily Override)?
                // Need map here? Or fetch logic... 
                // For list of absent slots, we typically care about classes that ARE happening but teacher isn't there.
                // If class is marked Absent/Trip, then NO PROXY is needed, right?
                // YES! So we should skip this slot if Class is Absent.
                
                // Fetch overrides if not already fetched? (This method fetches entries for whole day)
                // Let's assume we need to instantiate check inside loop or pre-fetch.
                // Pre-fetching...
                if (!isset($classAbsentMap)) {
                     $overridesMap = $this->dailyOverridesModel->getOverridesMap($date);
                     $classAbsentMap = $overridesMap['CLASS_ABSENT'] ?? [];
                }
                
                $cid = $lecture['class_id'];
                $p = $lecture['period_no'];
                if (isset($classAbsentMap[$cid]) && (isset($classAbsentMap[$cid][$p]) || isset($classAbsentMap[$cid][0]))) {
                    continue; // Class is away, no proxy needed.
                }

                // 2. Is Period Blocked for THIS Class (Weekly Block)?
                if (isset($blockedToday[$lecture['period_no']][$lecture['class_id']])) {
                    continue;
                }

                // Check if this period has multiple entries (i.e., is it a group class?)
                // We need to differentiate if there are other classes in the same slot
                $classEntries = $this->timetableModel->getClassPeriodEntries($lecture['class_id'], $dayOfWeek, $lecture['period_no']);
                $isGroupClass = count($classEntries) > 1;

                // Determine Group Name
                $groupName = $lecture['group_name'];
                if (empty($groupName) && $isGroupClass) {
                    $groupName = $lecture['subject_name'];
                }

                $slotKey = $teacher['id'] . '|' . $lecture['period_no'] . '|' . $lecture['class_id'] . '|' . $lecture['subject_id'];
                
                // Try to find assignment with specific subject first, then fallback to general (legacy)
                $assignmentId = $existingAssignments[$slotKey] ?? $existingAssignments[$teacher['id'] . '|' . $lecture['period_no'] . '|' . $lecture['class_id']] ?? null;

                $slots[] = [
                    'teacher_id' => $teacher['id'],
                    'teacher_name' => $teacher['name'],
                    'period_no' => $lecture['period_no'],
                    'class_id' => $lecture['class_id'],
                    'standard' => $lecture['standard'],
                    'division' => $lecture['division'],
                    'subject_id' => $lecture['subject_id'],
                    'subject_name' => $lecture['subject_name'],
                    'group_name' => $groupName,
                    'is_group_class' => $isGroupClass, 
                    'assigned_proxy_id' => $assignmentId
                ];
            }
        }

        return $slots;
    }
    /**
     * Helper to get Section Priorities for all classes.
     * Returns [class_id => priority_int]
     */
    private function getClassPriorities() {
        // We need to join classes -> sections
        require_once __DIR__ . '/../models/Classes.php';
        $classModel = new Classes();
        $classes = $classModel->getAll(); // returns row with section_name, need priority?
        
        // Classes::getAll() query: SELECT c.*, s.name... 
        // We need 'priority' from sections. 
        // Let's modify the query locally or assume check if 'priority' is in result.
        // It is NOT in getAll(). Need to fetch it.
        
        // Better: Fetch all sections map [id => priority]
        // Then map class -> section_id -> priority
        require_once __DIR__ . '/../models/Section.php';
        // Assume Section model exists or query directly. 
        // Let's query directly via PDO for efficiency here or add method to Settings/Master
        
        // Fallback: Query manually here for speed
        $stmt = $this->pdo->query("SELECT id, priority FROM sections");
        $sectionPriorities = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [id => priority]
        
        $map = [];
        foreach ($classes as $c) {
             $sid = $c['section_id'];
             $map[$c['id']] = $sectionPriorities[$sid] ?? 99; // Default low priority
        }
        return $map;
    }

    /**
     * Helper to get Teacher Main Section Priority.
     * Returns [teacher_id => priority_int]
     */
    private function getTeacherPriorities() {
        // Teacher -> teacher_sections -> sections.priority
        // If teacher has multiple sections, take the HIGHEST priority (Min Int value)? 
        // Or Main Section? Let's assume Highest (Min).
        
        $sql = "
            SELECT ts.teacher_id, MIN(s.priority) as priority
            FROM teacher_sections ts
            JOIN sections s ON ts.section_id = s.id
            GROUP BY ts.teacher_id
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    /**
     * Helper to get Teacher Subject Expertise.
     * Returns [teacher_id => [subject_id => true]]
     */
    private function getTeacherSubjectsMap() {
        // Get explicit assignments first
        $sql = "SELECT teacher_id, subject_id FROM teacher_subjects";
        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll();
        
        $map = [];
        foreach ($rows as $r) {
            $map[$r['teacher_id']][$r['subject_id']] = true;
        }
        
        // ALSO: Check Timetable. If a teacher is teaching a subject in timetable, they know it.
        // (Optional: might be redundant if teacher_subjects is maintained well, but safer)
        $sql2 = "SELECT DISTINCT teacher_id, subject_id FROM timetable";
        $stmt2 = $this->pdo->query($sql2);
        $rows2 = $stmt2->fetchAll();
        foreach ($rows2 as $r) {
             $map[$r['teacher_id']][$r['subject_id']] = true;
        }
        
        return $map;
    }
    
    /**
     * Score a candidate for a specific slot.
     */
    private function calculateScore($candidate, $classPriority, $slotSubjectId, $teacherPriorities, $teacherSubjectsMap) {
        $tid = $candidate['id'];
        $score = 0;
        
        // 1. SECTION MATCH (Base Score)
        $pTeach = $teacherPriorities[$tid] ?? 99;
        $matchesSection = false;
        
        if ($pTeach == 99) {
            // FLOATING TEACHER (No section assignment, e.g. extra staff, librarian, etc.)
            // Give a neutral base score - they're available but not section-specific
            $score += 35; // Neutral baseline - lower than perfect match, higher than large gap
        } elseif ($pTeach == $classPriority) {
            $score += 100; // Perfect Section Match
            $matchesSection = true;
        } elseif ($pTeach > $classPriority) {
            // Teacher is from Lower priority (e.g. Class=1, Teacher=2)
            $diff = $pTeach - $classPriority;
            $score += max(0, 50 - ($diff * 10)); // Gap 1=40, Gap 2=30...
        } else {
             // Teacher is from Higher priority (e.g. Class=2, Teacher=1)
             // Use senior resource for junior class? Allowed but less preferred
             $diff = $classPriority - $pTeach;
             $score += max(0, 30 - ($diff * 10));
        }
        
        // 2. SUBJECT EXPERTISE (Bonus)
        if ($slotSubjectId && isset($teacherSubjectsMap[$tid][$slotSubjectId])) {
            $score += 50; // Huge Bonus
        }
        
        // 3. LOAD BALANCING (Tie Breaker)
        // Add Free Periods Count (0 to 8 usually)
        $score += $candidate['free_periods'];
        
        return $score;
    }

    /**
     * AUTO ALLOCATE ALL EMPTY SLOTS
     */
    /**
     * AUTO ALLOCATE ALL EMPTY SLOTS
     */
    public function autoAllocateAll($date) {
        $emptySlots = $this->getAbsentSlots($date); 
        
        // 2. Pre-load Maps for efficiency
        $classPriorities = $this->getClassPriorities();
        $teacherPriorities = $this->getTeacherPriorities();
        $teacherSubjectsMap = $this->getTeacherSubjectsMap();
        
        $assignmentsMade = 0;
        $details = [];

        // TRACKING STATE FOR THIS BATCH RUN
        $assignedInPeriod = []; // [period_no => [teacher_id => true]]
        $assignedCount = [];    // [teacher_id => count]

        foreach ($emptySlots as $slot) {
            if (!empty($slot['assigned_proxy_id'])) {
                continue; // Already assigned
            }
            if ($slot['is_group_class']) {
                 // Optional: Skip complex group classes
            }
            
            // Get Candidates for this specific slot
            // Note: This fetches from DB, so it doesn't know about assignments we just made in variables
            $candidates = $this->getAvailableCandidates($date, $slot['period_no']);
            
            if (empty($candidates)) {
                $details[] = "Slot {$slot['standard']}-{$slot['division']} (Per {$slot['period_no']}): No candidates found.";
                continue;
            }
            
            // FILTER & SCORE CANDIDATES
            $validCandidates = [];
            
            foreach ($candidates as $cand) {
                $tid = $cand['id'];
                
                // CHECK 1: DOUBLE BOOKING IN THIS RUN
                if (isset($assignedInPeriod[$slot['period_no']][$tid])) {
                    continue; // Already assigned in this period during this run
                }
                
                // CHECK 2: LOAD & MINIMUM FREE RULE
                // Current Free in DB: $cand['free_periods']
                // Less: Assignments made in this run: $assignedCount[$tid]
                $currentAssignments = $assignedCount[$tid] ?? 0;
                $realFreePeriods = $cand['free_periods'] - $currentAssignments;
                
                // Rule: "if teacher has 1 free period then do not give them proxy"
                // Meaning, must have at least 2 free periods to start with, or result must be >= 1?
                // Usually means: Can only assign if remaining free AFTER this assignment would be >= 0?
                // User said: "if teacher has 1 free period then do not give them proxy". 
                // This implies they need to KEEP at least 1 free period? Or they simply are too busy?
                // Interpretation: Threshold is > 1. i.e. 2 or more.
                if ($realFreePeriods <= 1) {
                    continue; // Preservation Rule: Don't consume their last free period (or single free period)
                }
                
                $cand['real_free_periods'] = $realFreePeriods;
                
                // SCORE
                $cand['score'] = $this->calculateScore(
                    $cand, 
                    $classPriorities[$slot['class_id']] ?? 99, 
                    $slot['subject_id'], 
                    $teacherPriorities, 
                    $teacherSubjectsMap
                );
                
                $validCandidates[] = $cand;
            }
            
            if (empty($validCandidates)) {
                $details[] = "Slot {$slot['standard']}-{$slot['division']} (Per {$slot['period_no']}): All candidates filtered out by rules.";
                continue;
            }
            
            // Sort by Score DESC
            usort($validCandidates, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            $bestCandidate = $validCandidates[0];
            
            // THRESHOLD CHECK
            // With floating teachers (35 base) + free periods (up to 8) = ~43 max without subject
            // With subject match: 35 + 50 + periods = ~93
            // Lowered threshold to 20 to allow more flexibility
            if ($bestCandidate['score'] < 20) {
                $details[] = "Slot {$slot['standard']}-{$slot['division']} (Per {$slot['period_no']}): Best candidate {$bestCandidate['name']} score {$bestCandidate['score']} too low.";
                continue;
            }
            
            // ALLOCATE
            try {
                $this->proxyModel->assign(
                    $date,
                    $slot['teacher_id'], // Absent Teacher
                    $bestCandidate['id'], // Proxy Teacher
                    $slot['class_id'],
                    $slot['period_no'],
                    'AUTO', // Mode
                    "Score: " . $bestCandidate['score'], // RuleApplied
                    $slot['subject_id'] // Ensure we track subject
                );
                
                // UPDATE STATE
                $assignmentsMade++;
                $tid = $bestCandidate['id'];
                $assignedInPeriod[$slot['period_no']][$tid] = true;
                if (!isset($assignedCount[$tid])) $assignedCount[$tid] = 0;
                $assignedCount[$tid]++;
                
                $details[] = "Assigned {$bestCandidate['name']} to {$slot['standard']}-{$slot['division']} (Score: {$bestCandidate['score']}).";
                
            } catch (Exception $e) {
                $details[] = "Error allocating {$slot['standard']}-{$slot['division']}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'count' => $assignmentsMade,
            'details' => $details
        ];
    }
}
