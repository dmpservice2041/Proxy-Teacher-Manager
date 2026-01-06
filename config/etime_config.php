<?php
/**
 * eTime Office API Configuration
 * Store API credentials and endpoints
 */

return [
    // API Credentials
    'corporate_id' => 'stmarys',
    'username' => 'stmays',
    'password' => 'Campus@123',
    
    // API Endpoints
    'base_url' => 'https://api.etimeoffice.com/api',
    'endpoints' => [
        'raw_punch_data' => '/DownloadPunchData',
        'raw_punch_data_mcid' => '/DownloadPunchDataMCID',
        'inout_punch_data' => '/DownloadInOutPunchData',
        'last_punch_data' => '/DownloadLastPunchData'
    ],
    
    // Default endpoint to use
    'default_endpoint' => 'inout_punch_data',
    
    // Timezone for date formatting
    'timezone' => 'Asia/Kolkata',
    
    // Attendance status mapping
    'status_mapping' => [
        'P' => 'Present',      // Full Present
        'P/2' => 'Present',    // Half Day Present
        'A' => 'Absent',       // Absent
        'WO' => 'Present',     // Week Off (mark as present)
        'L' => 'Absent',       // Leave (mark as absent)
    ]
];
