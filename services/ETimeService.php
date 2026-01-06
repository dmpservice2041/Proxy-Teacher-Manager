<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/etime_config.php';
require_once __DIR__ . '/../models/Attendance.php';
require_once __DIR__ . '/../models/Teacher.php';

/**
 * ETimeService - Service to interact with eTime Office API
 * Fetches attendance data from biometric system
 */
class ETimeService {
    private $db;
    private $config;
    private $authHeader;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->config = require __DIR__ . '/../config/etime_config.php';
        
        // Generate Base64 encoded authorization header
        $authString = sprintf(
            '%s:%s:%s:true',
            $this->config['corporate_id'],
            $this->config['username'],
            $this->config['password']
        );
        $this->authHeader = 'Basic ' . base64_encode($authString);
    }

    /**
     * Fetch attendance data for a specific date using In/Out Punch Data API
     * @param string $date Date in Y-m-d format
     * @return array Success status and message
     */
    public function fetchDailyAttendance($date) {
        try {
            // Convert date to eTime Office format (dd/MM/yyyy)
            $etimeDate = date('d/m/Y', strtotime($date));
            
            // Build API URL
            $url = sprintf(
                '%s%s?Empcode=ALL&FromDate=%s&ToDate=%s',
                $this->config['base_url'],
                $this->config['endpoints']['inout_punch_data'],
                $etimeDate,
                $etimeDate
            );

            // Make API request
            $response = $this->makeApiRequest($url);
            
            if ($response['Error'] === false && isset($response['InOutPunchData'])) {
                $result = $this->processAttendanceData($response['InOutPunchData'], $date);
                
                // Mark employees not in API as absent
                $missingResult = $this->markMissingEmployeesAsAbsent($response['InOutPunchData'], $date);
                
                // Get statistics about all employees
                $teacherModel = new Teacher();
                $allActiveTeachers = $teacherModel->getAllActive();
                $allTeachers = $teacherModel->getAllWithDetails();
                
                // Count how many teachers (active and inactive) have attendance records for this date
                $attendanceModel = new Attendance();
                $attendanceForDate = $attendanceModel->getAllForDate($date);
                $attendanceTeacherIds = array_unique(array_column($attendanceForDate, 'teacher_id'));
                
                // Count all teachers (active and inactive) with empcodes
                $totalActive = count($allActiveTeachers);
                $totalAll = count($allTeachers);
                $totalActiveWithEmpcode = 0;
                $totalActiveWithoutEmpcode = 0;
                $totalInactiveWithEmpcode = 0;
                $totalInactiveWithoutEmpcode = 0;
                $allTeachersWithEmpcodeIds = [];
                $activeTeachersWithEmpcodeIds = [];
                
                foreach ($allTeachers as $teacher) {
                    if (!empty($teacher['empcode'])) {
                        $allTeachersWithEmpcodeIds[] = $teacher['id'];
                        if ($teacher['is_active']) {
                            $totalActiveWithEmpcode++;
                            $activeTeachersWithEmpcodeIds[] = $teacher['id'];
                        } else {
                            $totalInactiveWithEmpcode++;
                        }
                    } else {
                        if ($teacher['is_active']) {
                            $totalActiveWithoutEmpcode++;
                        } else {
                            $totalInactiveWithoutEmpcode++;
                        }
                    }
                }
                
                // Count how many teachers (all) with empcodes have attendance records
                $allWithAttendance = count(array_intersect($attendanceTeacherIds, $allTeachersWithEmpcodeIds));
                $activeWithAttendance = count(array_intersect($attendanceTeacherIds, $activeTeachersWithEmpcodeIds));
                
                $totalProcessed = $result['processed'] + $missingResult['marked'];
                
                // Combine skipped records
                $allSkipped = array_merge($result['skipped'], $missingResult['marked_absent']);
                
                // Mark employees without empcodes as absent (they can't be fetched from API) - ALL teachers
                foreach ($allTeachers as $teacher) {
                    if (empty($teacher['empcode'])) {
                        try {
                            // Check if already has attendance record
                            $currentAtt = $attendanceModel->getAttendanceForTeacher($teacher['id'], $date);
                            if (!$currentAtt) {
                                // No attendance record - mark as absent since we can't fetch from API
                                $attendanceModel->markAttendance($teacher['id'], $date, 'Absent', 'API');
                                $totalProcessed++;
                                $missingResult['marked']++;
                            }
                            
                            $allSkipped[] = [
                                'empcode' => 'N/A',
                                'name' => $teacher['name'],
                                'reason' => 'No employee code - marked as absent (cannot fetch from API)'
                            ];
                        } catch (Exception $e) {
                            error_log("Error marking teacher without empcode {$teacher['name']} as absent: " . $e->getMessage());
                            $allSkipped[] = [
                                'empcode' => 'N/A',
                                'name' => $teacher['name'],
                                'reason' => 'No employee code - error marking as absent: ' . $e->getMessage()
                            ];
                        }
                    }
                }
                
                // Recalculate total processed after marking employees without empcodes
                $totalProcessed = $result['processed'] + $missingResult['marked'];
                
                // Recalculate attendance counts after all processing
                $attendanceForDate = $attendanceModel->getAllForDate($date);
                $attendanceTeacherIds = array_unique(array_column($attendanceForDate, 'teacher_id'));
                $allWithAttendance = count(array_intersect($attendanceTeacherIds, $allTeachersWithEmpcodeIds));
                $activeWithAttendance = count(array_intersect($attendanceTeacherIds, $activeTeachersWithEmpcodeIds));
                
                // Recalculate present/absent counts
                $presentCount = 0;
                $absentCount = 0;
                foreach ($attendanceForDate as $att) {
                    if ($att['status'] === 'Present') {
                        $presentCount++;
                    } else {
                        $absentCount++;
                    }
                }
                
                $unprocessed = ($totalActiveWithEmpcode + $totalInactiveWithEmpcode) - $allWithAttendance;
                
                // Log success with details
                $this->logSync($date, 'DAILY', 'SUCCESS', $totalProcessed);
                
                $message = "Successfully processed {$result['processed']} attendance records from API.";
                
                if ($missingResult['marked'] > 0) {
                    $message .= " Marked {$missingResult['marked']} missing employees as absent.";
                }
                
                // Add summary information (calculated after all processing)
                $message .= "<br><small><i class='fas fa-info-circle'></i> ";
                $message .= "Total Employees: {$totalAll} (Active: {$totalActive}, Inactive: " . ($totalAll - $totalActive) . ") | ";
                $message .= "With Empcode: " . ($totalActiveWithEmpcode + $totalInactiveWithEmpcode) . " | ";
                $message .= "API Records: {$result['total']} | ";
                $message .= "Processed: {$totalProcessed} | ";
                $message .= "Present: {$presentCount} | ";
                $message .= "Absent: {$absentCount}";
                
                if ($unprocessed > 0) {
                    $message .= " | <span class='text-warning'><strong>⚠️ Unprocessed: {$unprocessed}</strong></span>";
                }
                $message .= "</small>";
                
                return [
                    'success' => true,
                    'message' => $message,
                    'records' => $totalProcessed,
                    'skipped' => $allSkipped,
                    'total_in_api' => $result['total'],
                    'stats' => [
                        'total_all' => $totalAll,
                        'total_active' => $totalActive,
                        'total_inactive' => $totalAll - $totalActive,
                        'with_empcode' => $totalActiveWithEmpcode + $totalInactiveWithEmpcode,
                        'active_with_empcode' => $totalActiveWithEmpcode,
                        'inactive_with_empcode' => $totalInactiveWithEmpcode,
                        'without_empcode' => $totalActiveWithoutEmpcode + $totalInactiveWithoutEmpcode,
                        'processed' => $totalProcessed,
                        'present' => $presentCount,
                        'absent' => $absentCount,
                        'all_with_attendance' => $allWithAttendance,
                        'active_with_attendance' => $activeWithAttendance,
                        'unprocessed' => $unprocessed
                    ]
                ];
            } else {
                $errorMsg = $response['Msg'] ?? 'Unknown API error';
                $this->logSync($date, 'DAILY', 'FAILED', 0, $errorMsg);
                
                return [
                    'success' => false,
                    'message' => "API Error: {$errorMsg}"
                ];
            }
            
        } catch (Exception $e) {
            $this->logSync($date, 'DAILY', 'FAILED', 0, $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process attendance data from API and update database
     * @param array $punchData Array of attendance records from API
     * @param string $date Date in Y-m-d format
     * @return int Number of records processed
     */
    private function processAttendanceData($punchData, $date) {
        $recordsProcessed = 0;
        $attendanceModel = new Attendance();
        $teacherModel = new Teacher();
        $skippedRecords = [];
        
        $totalRecords = count($punchData);

        // Debug logging removed
        
        foreach ($punchData as $record) {
            try {
                $empcode = $record['Empcode'] ?? null;
                $name = $record['Name'] ?? 'Unknown';
                
                // Handle status more robustly - trim whitespace and handle empty strings
                $status = trim($record['Status'] ?? '');
                if ($status === '') {
                    $status = 'A'; // Default to Absent if status is empty
                }
                
                if (!$empcode) {
                    $skippedRecords[] = [
                        'empcode' => 'N/A',
                        'name' => $name,
                        'reason' => 'No employee code in API response'
                    ];
                    continue;
                }

                // Find teacher by empcode (include inactive teachers too)
                // Normalize empcode for lookup (ensure string)
                $empcodeNormalized = trim((string)$empcode);
                $teacher = $teacherModel->findByEmpcode($empcodeNormalized);
                if (!$teacher) {
                    $skippedRecords[] = [
                        'empcode' => $empcode,
                        'name' => $name,
                        'reason' => 'Employee code not found in database'
                    ];
                    continue;
                }
                
                // Process both active and inactive teachers (API sends all employees)

                // Extract times (Keys are Case Sensitive from API)
                $inTime = $record['INTime'] ?? null;
                $outTime = $record['OUTTime'] ?? null;
                
                // Reset times if placeholder
                if ($inTime == '--:--' || $inTime === '' || $inTime === null) $inTime = null;
                if ($outTime == '--:--' || $outTime === '' || $outTime === null) $outTime = null;

                // Map status from API to our system
                $attendanceStatus = $this->mapStatus($status);
                
                // CRITICAL: If both INTime and OUTTime are null/empty, employee is definitely Absent
                // This is the most reliable indicator - no punch data = absent
                if ($inTime === null && $outTime === null) {
                    $attendanceStatus = 'Absent';
                    // Log for debugging
                    if ($status !== 'A' && $status !== '') {
                        error_log("Employee {$empcode} ({$name}) has no punch times - marking as Absent (API status was: '{$status}')");
                    }
                } else {
                    // Has times, so use the mapped status
                    // But double-check: if status was 'A' or empty, force Absent
                    if ($status === 'A' || $status === '') {
                        $attendanceStatus = 'Absent';
                    }
                }
                
                // Mark or update attendance with times
                $attendanceModel->markAttendance($teacher['id'], $date, $attendanceStatus, 'API', $inTime, $outTime);
                $recordsProcessed++;
            } catch (Exception $e) {
                $skippedRecords[] = [
                    'empcode' => $empcode ?? 'Unknown',
                    'name' => $name ?? 'Unknown',
                    'reason' => 'Error: ' . $e->getMessage()
                ];
                error_log("ETime API Processing Error for {$empcode}: " . $e->getMessage());
            }
        }

        return [
            'total' => $totalRecords,
            'processed' => $recordsProcessed,
            'skipped' => $skippedRecords
        ];
    }

    /**
     * Mark employees not in API response as absent
     * @param array $punchData Array of attendance records from API
     * @param string $date Date in Y-m-d format
     * @return array Results of marking missing employees
     */
    private function markMissingEmployeesAsAbsent($punchData, $date) {
        $teacherModel = new Teacher();
        $attendanceModel = new Attendance();
        
        // Get ALL teachers (active and inactive) with empcodes - API sends all employees
        $allTeachers = $teacherModel->getAllWithDetails();
        
        // Extract employee codes from API response (normalize for comparison)
        // Convert to strings and normalize for proper comparison
        $apiEmpcodes = [];
        $apiEmpcodesNormalized = [];
        foreach ($punchData as $record) {
            if (isset($record['Empcode'])) {
                $empcode = trim((string)$record['Empcode']); // Ensure string
                $apiEmpcodes[] = $empcode;
                $apiEmpcodesNormalized[strtolower($empcode)] = $empcode; // Case-insensitive lookup
            }
        }
        
        $markedCount = 0;
        $markedAbsent = [];
        
        // Find teachers not in API response OR teachers in API but with Absent status
        foreach ($allTeachers as $teacher) {
            // Skip if no empcode
            if (empty($teacher['empcode'])) {
                continue;
            }
            
            $teacherEmpcode = trim((string)$teacher['empcode']); // Ensure string
            $teacherEmpcodeLower = strtolower($teacherEmpcode);
            
            // Check if this teacher's empcode is in the API response
            // Use strict comparison and normalized lookup
            $isInApi = in_array($teacherEmpcode, $apiEmpcodes, true) || 
                      isset($apiEmpcodesNormalized[$teacherEmpcodeLower]);
            
            if (!$isInApi) {
                try {
                    // Check if already locked
                    if ($attendanceModel->isLocked($teacher['id'], $date)) {
                        continue;
                    }
                    
                    // Mark as absent
                    $attendanceModel->markAttendance($teacher['id'], $date, 'Absent', 'API');
                    $markedCount++;
                    
                    $markedAbsent[] = [
                        'empcode' => $teacherEmpcode,
                        'name' => $teacher['name'],
                        'reason' => 'Not in API response - marked as absent'
                    ];
                } catch (Exception $e) {
                    error_log("Error marking teacher {$teacherEmpcode} as absent: " . $e->getMessage());
                }
            } else {
                // Teacher IS in API response - verify their status is correctly set
                // Find the record in API data to check status
                $apiRecord = null;
                foreach ($punchData as $record) {
                    $recordEmpcode = trim((string)($record['Empcode'] ?? ''));
                    if (strtolower($recordEmpcode) === $teacherEmpcodeLower) {
                        $apiRecord = $record;
                        break;
                    }
                }
                
                if ($apiRecord) {
                    // Check if status indicates absent but database might have wrong status
                    $apiStatus = trim($apiRecord['Status'] ?? '');
                    $apiInTime = $apiRecord['INTime'] ?? null;
                    $apiOutTime = $apiRecord['OUTTime'] ?? null;
                    
                    // Check if times are empty/null
                    if ($apiInTime == '--:--' || $apiInTime === '' || $apiInTime === null) $apiInTime = null;
                    if ($apiOutTime == '--:--' || $apiOutTime === '' || $apiOutTime === null) $apiOutTime = null;
                    
                    // If no times OR status is A/empty, employee should be Absent
                    $shouldBeAbsent = ($apiInTime === null && $apiOutTime === null) || 
                                     ($apiStatus === '' || strtoupper($apiStatus) === 'A');
                    
                    if ($shouldBeAbsent) {
                        // Status is Absent - ensure database reflects this
                        try {
                            // Check current status in database
                            $currentAtt = $attendanceModel->getAttendanceForTeacher($teacher['id'], $date);
                            
                            // If database shows Present but API says Absent, fix it
                            if ($currentAtt && $currentAtt['status'] === 'Present') {
                                $attendanceModel->markAttendance($teacher['id'], $date, 'Absent', 'API', $apiInTime, $apiOutTime);
                                $markedCount++;
                                $markedAbsent[] = [
                                    'empcode' => $teacherEmpcode,
                                    'name' => $teacher['name'],
                                    'reason' => 'Status corrected: API shows Absent (Status: ' . ($apiStatus ?: 'empty') . ', Times: ' . ($apiInTime ?: 'none') . ')'
                                ];
                            } elseif (!$currentAtt) {
                                // No record exists, create Absent record
                                $attendanceModel->markAttendance($teacher['id'], $date, 'Absent', 'API', $apiInTime, $apiOutTime);
                                $markedCount++;
                            }
                        } catch (Exception $e) {
                            error_log("Error correcting status for teacher {$teacherEmpcode}: " . $e->getMessage());
                        }
                    }
                }
            }
        }
        
        return [
            'marked' => $markedCount,
            'marked_absent' => $markedAbsent
        ];
    }

    /**
     * Map eTime Office status to our system status
     * @param string $apiStatus Status from API (e.g., "P", "P/2", "A")
     * @return string Mapped status ("Present" or "Absent")
     */
    private function mapStatus($apiStatus) {
        // Trim and normalize status
        $apiStatus = trim($apiStatus);
        
        // Handle empty status - always Absent
        if ($apiStatus === '') {
            return 'Absent';
        }
        
        // Extract base status (handle cases like "P/2")
        $baseStatus = strtoupper(explode('/', $apiStatus)[0]);
        
        // Explicitly check for Absent indicators first
        if ($baseStatus === 'A' || $baseStatus === 'ABSENT' || $baseStatus === 'L' || $baseStatus === 'LEAVE') {
            return 'Absent';
        }
        
        // Check if status is in mapping (case-insensitive)
        if (isset($this->config['status_mapping'][$baseStatus])) {
            return $this->config['status_mapping'][$baseStatus];
        }
        
        // Default to Absent if status is unknown (safer default)
        return 'Absent';
    }

    /**
     * Make HTTP request to eTime Office API
     * @param string $url API endpoint URL
     * @return array Decoded JSON response
     */
    private function makeApiRequest($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->authHeader,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL Error: {$error}");
        }
        
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: {$httpCode}");
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Decode Error: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Log API sync operation
     */
    private function logSync($date, $fetchType, $status, $recordsProcessed = 0, $errorMessage = null) {
        $stmt = $this->db->prepare("
            INSERT INTO api_sync_log 
            (sync_date, fetch_type, status, records_processed, error_message) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $date,
            $fetchType,
            $status,
            $recordsProcessed,
            $errorMessage
        ]);
    }

    /**
     * Get last sync information
     * @return array|null Last sync record
     */
    public function getLastSync() {
        $stmt = $this->db->prepare("
            SELECT * FROM api_sync_log 
            ORDER BY synced_at DESC 
            LIMIT 1
        ");
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetch all unique teachers from API (for importing teacher list)
     * Uses a recent date range to get comprehensive employee list
     * @param int $daysBack Number of days to look back for attendance data
     * @return array Success status, message, and unique teachers data
     */
    public function fetchAllTeachers($daysBack = 90) {
        try {
            // Use a date range to get comprehensive list
            $endDate = date('d/m/Y');
            $startDate = date('d/m/Y', strtotime("-{$daysBack} days"));
            
            // Build API URL
            $url = sprintf(
                '%s%s?Empcode=ALL&FromDate=%s&ToDate=%s',
                $this->config['base_url'],
                $this->config['endpoints']['inout_punch_data'],
                $startDate,
                $endDate
            );

            // Make API request
            $response = $this->makeApiRequest($url);
            
            if ($response['Error'] === false && isset($response['InOutPunchData'])) {
                $uniqueTeachers = [];
                
                // Extract unique teachers by empcode
                foreach ($response['InOutPunchData'] as $record) {
                    $empcode = $record['Empcode'] ?? null;
                    $name = $record['Name'] ?? null;
                    
                    if ($empcode && $name) {
                        if (!isset($uniqueTeachers[$empcode])) {
                            $uniqueTeachers[$empcode] = [
                                'empcode' => $empcode,
                                'name' => $name
                            ];
                        }
                    }
                }
                
                return [
                    'success' => true,
                    'message' => 'Successfully fetched ' . count($uniqueTeachers) . ' unique teachers from API.',
                    'teachers' => array_values($uniqueTeachers)
                ];
            } else {
                $errorMsg = $response['Msg'] ?? 'Unknown API error';
                return [
                    'success' => false,
                    'message' => "API Error: {$errorMsg}",
                    'teachers' => []
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'teachers' => []
            ];
        }
    }

    /**
     * Import teachers from API into database
     * @param int $defaultSectionId Default section ID to assign teachers to
     * @return array Import results
     */
    public function importTeachersFromAPI($defaultSectionId = null) {
        $result = $this->fetchAllTeachers();
        
        if (!$result['success']) {
            return $result;
        }
        
        $teacherModel = new Teacher();
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($result['teachers'] as $teacherData) {
            try {
                // Check if teacher already exists
                $existing = $teacherModel->findByEmpcode($teacherData['empcode']);
                
                if ($existing) {
                    $skipped++;
                    continue;
                }
                
                // Add new teacher (simplified - no section needed)
                $teacherModel->add(
                    $teacherData['name'],
                    $teacherData['empcode']
                );
                
                $imported++;
            } catch (Exception $e) {
                $errors[] = "Failed to import {$teacherData['name']}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'message' => "Import complete: {$imported} teachers imported, {$skipped} already existed.",
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }
}
