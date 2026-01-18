<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'models/Teacher.php';
require_once 'models/Section.php';
require_once 'models/Classes.php';
require_once 'models/Subject.php';
require_once 'models/Settings.php';
require_once 'services/ETimeService.php';
require_once 'models/User.php';

$userModel = new User();

$tab = $_GET['tab'] ?? 'teachers';
$editId = $_GET['edit'] ?? null;
$sortBy = $_GET['sort'] ?? 'id';
$sortOrder = $_GET['order'] ?? 'asc';
$pdo = Database::getInstance()->getConnection();
$message = '';
$error = '';

// Models
$teacherModel = new Teacher();
$sectionModel = new Section();
$classModel = new Classes();

$subjectModel = new Subject();
$settingsModel = new Settings();

// --- Handle CRUD Actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // TEACHER CRUD
               if (isset($_POST['add_teacher'])) {
            $sectionIds = $_POST['section_ids'] ?? [];
            $teacherModel->add($_POST['name'], $_POST['empcode'] ?: null, $sectionIds);
            $message = "Teacher added successfully.";
        }
        elseif (isset($_POST['edit_teacher'])) {
            $sectionIds = $_POST['section_ids'] ?? [];
            $teacherModel->update(
                $_POST['id'], 
                $_POST['name'], 
                $_POST['empcode'] ?: null, 
                isset($_POST['is_active']) ? 1 : 0,
                $sectionIds
            );
            $message = "Teacher updated successfully.";
            $editId = null; // Clear edit mode
        }
        elseif (isset($_POST['delete_teacher'])) {
            $teacherModel->delete($_POST['id']);
            $message = "Teacher deleted successfully.";
        }
        elseif (isset($_POST['toggle_teacher_active'])) {
            $teacherModel->toggleActive($_POST['id']);
            $message = "Teacher status updated.";
        }
        elseif (isset($_POST['import_teachers_csv'])) {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
                $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
                $count = 0;
                $skipped = 0;
                
                // Skip header row
                fgetcsv($file);
                
                while (($row = fgetcsv($file)) !== false) {
                    $name = trim($row[0] ?? '');
                    $empcode = trim($row[1] ?? '');
                    
                    if (empty($name)) continue;
                    
                    // Simple duplicate check by EmpCode (if provided) or Name
                    $exists = false;
                    if (!empty($empcode)) {
                        $stmt = $pdo->prepare("SELECT id FROM teachers WHERE empcode = ?");
                        $stmt->execute([$empcode]);
                        if ($stmt->fetch()) $exists = true;
                    } 
                    
                    if (!$exists) {
                         // Fallback check by name if no empcode or empcode didn't match
                         // (Optional: you might strictly want unique empcodes but allow same names)
                         // For now, let's just create it if empcode was handled or empty.
                         try {
                             $teacherModel->add($name, $empcode ?: null);
                             $count++;
                         } catch (Exception $e) {
                             $skipped++;
                         }
                    } else {
                        $skipped++;
                    }
                }
                fclose($file);
                $message = "Imported $count teachers successfully. Skipped $skipped duplicates/errors.";
            } else {
                 $error = "Please upload a valid CSV file.";
            }
        }
        elseif (isset($_POST['import_teachers_from_api'])) {
            // Import teachers from eTime Office API
            $etimeService = new ETimeService();
            
            $result = $etimeService->importTeachersFromAPI(null); // No section needed
            
            if ($result['success']) {
                $message = $result['message'];
                if (!empty($result['errors'])) {
                    $error = implode('<br>', $result['errors']);
                }
            } else {
                $error = $result['message'];
            }
        }
        
        // TEACHER-SUBJECT
        elseif (isset($_POST['add_teacher_subject'])) {
            $teacherId = $_POST['teacher_id'];
            $subjectIds = $_POST['subject_ids'] ?? [];
            $count = 0;
            foreach ($subjectIds as $sid) {
                $teacherModel->assignSubject($teacherId, $sid);
                $count++;
            }
            $message = $count > 0 ? "$count subject(s) assigned successfully." : "No subjects selected.";
        }
        elseif (isset($_POST['delete_teacher_subject'])) {
            $stmt = $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ? AND subject_id = ?");
            $stmt->execute([$_POST['teacher_id'], $_POST['subject_id']]);
            $message = "Subject assignment removed.";
        }
        
        // SECTION CRUD
        elseif (isset($_POST['add_section'])) {
            $sectionModel->add($_POST['name'], $_POST['priority']);
            $message = "Section added successfully.";
        }
        elseif (isset($_POST['edit_section'])) {
            $sectionModel->update($_POST['id'], $_POST['name'], $_POST['priority']);
            $message = "Section updated successfully.";
            $editId = null;
        }
        elseif (isset($_POST['delete_section'])) {
            $sectionModel->delete($_POST['id']);
            $message = "Section deleted successfully.";
        }
        
        // CLASS CRUD
        elseif (isset($_POST['add_class'])) {
            $classModel->add($_POST['standard'], $_POST['division'], $_POST['section_id']);
            $message = "Class added successfully.";
        }
        elseif (isset($_POST['edit_class'])) {
            $classModel->update($_POST['id'], $_POST['standard'], $_POST['division'], $_POST['section_id']);
            $message = "Class updated successfully.";
            $editId = null;
        }
        elseif (isset($_POST['delete_class'])) {
            $classModel->delete($_POST['id']);
            $message = "Class deleted successfully.";
        }
        
        // SUBJECT CRUD
        elseif (isset($_POST['add_subject'])) {
            $subjectModel->add($_POST['name']);
            $message = "Subject added successfully.";
        }
        elseif (isset($_POST['edit_subject'])) {
            $subjectModel->update($_POST['id'], $_POST['name']);
            $message = "Subject updated successfully.";
            $editId = null;
        }
        elseif (isset($_POST['delete_subject'])) {
            $subjectModel->delete($_POST['id']);
            $message = "Subject deleted successfully.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// --- Fetch Data for Display ---
$activeSections = $sectionModel->getAllOrderedByPriority();

if ($tab == 'teachers') {
    $allowedSortColumns = ['id' => 't.id', 'name' => 't.name', 'empcode' => 't.empcode'];
    $sortColumn = $allowedSortColumns[$sortBy] ?? 't.id';
    $sortDirection = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
    
    $stmt = $pdo->query("
        SELECT t.*, GROUP_CONCAT(s.name SEPARATOR ', ') as section_names
        FROM teachers t
        LEFT JOIN teacher_sections ts ON t.id = ts.teacher_id
        LEFT JOIN sections s ON ts.section_id = s.id
        GROUP BY t.id
        ORDER BY {$sortColumn} {$sortDirection}
    ");
    $data = $stmt->fetchAll();
    $headers = ['ID', 'Name', 'Emp Code', 'Department', 'Status', 'Actions'];
    $editRecord = $editId ? $teacherModel->find($editId) : null;
    if ($editRecord) {
        $sections = $teacherModel->getSections($editId);
        $editRecord['section_ids'] = array_column($sections, 'id');
    }
} elseif ($tab == 'sections') {
    $data = $sectionModel->getAllOrderedByPriority();
    $headers = ['ID', 'Name', 'Priority', 'Actions'];
    $editRecord = $editId ? $sectionModel->find($editId) : null;
} elseif ($tab == 'classes') {
    $data = $classModel->getAll();
    $headers = ['ID', 'Standard', 'Division', 'Section', 'Actions'];
    $editRecord = $editId ? $classModel->find($editId) : null;
} elseif ($tab == 'subjects') {
    $data = $subjectModel->getAll();
    $headers = ['ID', 'Name', 'Status', 'Actions'];
    $editRecord = $editId ? $subjectModel->find($editId) : null;
}
?>

<style>
    :root {
        --primary-color: #4361ee;
        --secondary-color: #3f37c9;
        --success-color: #2ec4b6;
        --warning-color: #e9c46a;
        --danger-color: #e63946;
        --bg-light: #f8f9fa;
        --card-shadow: 0 4px 20px rgba(0,0,0,0.05);
        --border-radius: 12px;
    }

    body {
        background-color: #f3f4f6;
    }

    .page-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        padding: 2rem 2.5rem;
        border-radius: 16px;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.2);
        border: none;
    }
    
    .page-title {
        color: white;
        font-weight: 700;
        font-size: 1.75rem;
        margin: 0;
        letter-spacing: -0.5px;
    }
    
    .page-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
        font-weight: 400;
    }

    /* Custom Navigation Tabs */
    .custom-tabs .nav-link {
        color: #6c757d;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        transition: all 0.2s;
    }

    .custom-tabs .nav-link:hover {
        color: var(--primary-color);
        background: transparent;
    }

    .custom-tabs .nav-link.active {
        color: var(--primary-color);
        border-bottom: 2px solid var(--primary-color);
        background: transparent;
        font-weight: 600;
    }

    /* Modern Cards */
    .card-modern {
        border: none;
        box-shadow: var(--card-shadow);
        border-radius: var(--border-radius);
        background: #fff;
        height: 100%;
        transition: transform 0.2s;
    }
    
    .card-header-modern {
        background: transparent;
        border-bottom: 1px solid #f0f0f0;
        padding: 1.25rem 1.5rem;
        font-weight: 600;
        color: #2b2d42;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Sticky Sidebar */
    .sticky-sidebar {
        position: sticky;
        top: 20px;
        z-index: 99;
    }

    /* Tables */
    .table-modern {
        vertical-align: middle;
    }
    .table-modern thead th {
        background: #f8f9fa;
        color: #6c757d;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-bottom: 2px solid #edf2f4;
        padding: 1rem 0.75rem;
    }
    .table-modern tbody td {
        padding: 1rem 0.75rem;
        border-bottom: 1px solid #f0f0f0;
    }
    .table-modern tbody tr:last-child td {
        border-bottom: none;
    }
    .table-modern tbody tr:hover {
        background-color: #fdfdfd;
    }

    /* Action Buttons */
    .btn-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.2s;
    }
    .btn-icon:hover {
        transform: translateY(-2px);
    }

    /* Forms */
    .form-label {
        font-weight: 500;
        color: #344767;
        font-size: 0.9rem;
    }
    .form-control, .form-select {
        border: 1px solid #dee2e6;
        padding: 0.6rem 0.75rem;
        border-radius: 8px;
        font-size: 0.95rem;
    }
    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
    }

    /* Badges */
    .badge-soft {
        padding: 0.4em 0.8em;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.75rem;
    }
    .badge-soft-success { background: rgba(46, 196, 182, 0.15); color: #208b81; }
    .badge-soft-danger { background: rgba(230, 57, 70, 0.15); color: #c92a35; }
    .badge-soft-warning { background: rgba(233, 196, 106, 0.2); color: #b08d28; }
    .badge-soft-secondary { background: rgba(108, 117, 125, 0.15); color: #5a6268; }

</style>

<div class="container-fluid px-4 mt-4 mb-5">
    
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h2 class="page-title mb-1">Master Data</h2>
            <p class="page-subtitle mb-0">Manage your institution's core data structure</p>
        </div>
        <div class="d-flex gap-2">
           <!-- Global actions can go here -->
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" style="border-left: 4px solid var(--success-color) !important;">
            <i class="fas fa-check-circle me-2"></i> <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" style="border-left: 4px solid var(--danger-color) !important;">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Navigation -->
    <ul class="nav custom-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'teachers' ? 'active' : ''; ?>" href="?tab=teachers">
                <i class="fas fa-chalkboard-teacher me-1"></i> Teachers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'sections' ? 'active' : ''; ?>" href="?tab=sections">
                <i class="fas fa-layer-group me-1"></i> Sections
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'classes' ? 'active' : ''; ?>" href="?tab=classes">
                <i class="fas fa-users me-1"></i> Classes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'subjects' ? 'active' : ''; ?>" href="?tab=subjects">
                <i class="fas fa-book me-1"></i> Subjects
            </a>
        </li>
    </ul>

    <?php
    // List Views Logic
    ?>
    <div class="row g-4">
        <!-- LIST VIEW -->
        <div class="col-lg-8">
            <div class="card card-modern">
                <div class="card-header-modern">
                    <div>
                        <span class="h6 mb-0"><?php echo ucfirst($tab); ?> List</span>
                        <span class="badge bg-light text-secondary ms-2 rounded-pill"><?php echo count($data); ?> records</span>
                    </div>
                    <?php if ($tab == 'teachers'): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#importTeachersModal">
                            <i class="fas fa-cloud-download-alt me-1"></i> Sync API
                        </button>
                        <button type="button" class="btn btn-sm btn-success rounded-pill px-3 ms-1" data-bs-toggle="modal" data-bs-target="#importCsvModal">
                            <i class="fas fa-file-excel me-1"></i> Import Excel
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                    <table class="table table-modern mb-0">
                        <thead>
                            <tr>
                                <?php if ($tab == 'teachers'): ?>
                                    <?php
                                    $sortableColumns = [
                                        'ID' => 'id',
                                        'Name' => 'name',
                                        'Emp Code' => 'empcode'
                                    ];
                                    foreach ($headers as $h):
                                        if (isset($sortableColumns[$h])):
                                            $column = $sortableColumns[$h];
                                            $newOrder = ($sortBy === $column && $sortOrder === 'asc') ? 'desc' : 'asc';
                                            $icon = '<i class="fas fa-sort text-muted ms-1 small" style="opacity: 0.3;"></i>';
                                            if ($sortBy === $column) {
                                                $icon = $sortOrder === 'asc' ? ' <i class="fas fa-sort-up text-primary small"></i>' : ' <i class="fas fa-sort-down text-primary small"></i>';
                                            }
                                            ?>
                                            <th style="cursor: pointer;" onclick="window.location='?tab=teachers&sort=<?php echo $column; ?>&order=<?php echo $newOrder; ?>'">
                                                <?php echo $h . $icon; ?>
                                            </th>
                                        <?php else: ?>
                                            <th><?php echo $h; ?></th>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach($headers as $h): ?><th><?php echo $h; ?></th><?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($data)): ?>
                                <tr>
                                    <td colspan="<?php echo count($headers); ?>" class="text-center py-5 text-muted">
                                        <div class="mb-2"><i class="fas fa-inbox fa-3x opacity-25"></i></div>
                                        No records found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($data as $row): ?>
                                <tr>
                                    <?php if ($tab == 'teachers'): ?>
                                        <td class="text-muted fw-bold">#<?php echo $row['id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center me-3" style="width: 35px; height: 35px; font-weight: bold;">
                                                    <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="font-monospace text-secondary small bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($row['empcode'] ?? '-'); ?></span></td>
                                        <td>
                                            <?php if(!empty($row['section_names'])): ?>
                                                <small class="text-dark bg-light px-2 py-1 rounded d-inline-block text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($row['section_names']); ?>">
                                                    <?php echo htmlspecialchars($row['section_names']); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="badge badge-soft-warning">Universal Floater</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if($row['is_active']): ?>
                                                <span class="badge badge-soft-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-soft-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?tab=teachers&edit=<?php echo $row['id']; ?>" class="btn btn-icon btn-light text-primary" title="Edit">
                                                    <i class="fas fa-pen small"></i>
                                                </a>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="toggle_teacher_active" value="1">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn btn-icon btn-light <?php echo $row['is_active'] ? 'text-secondary' : 'text-success'; ?>" title="<?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                        <i class="fas <?php echo $row['is_active'] ? 'fa-ban' : 'fa-check'; ?> small"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this teacher? This will fail if teacher has historical records.');">
                                                    <input type="hidden" name="delete_teacher" value="1">
                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="btn btn-icon btn-light text-danger" title="Delete">
                                                        <i class="fas fa-trash small"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php elseif ($tab == 'sections'): ?>
                                        <td class="text-muted">#<?php echo $row['id']; ?></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo $row['priority']; ?></span></td>
                                        <td>
                                            <a href="?tab=sections&edit=<?php echo $row['id']; ?>" class="btn btn-icon btn-light text-primary"><i class="fas fa-pen small"></i></a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                                <input type="hidden" name="delete_section" value="1">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-icon btn-light text-danger"><i class="fas fa-trash small"></i></button>
                                            </form>
                                        </td>
                                    <?php elseif ($tab == 'classes'): ?>
                                        <td class="text-muted">#<?php echo $row['id']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['standard']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['division']); ?></span></td>
                                        <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                        <td>
                                            <a href="?tab=classes&edit=<?php echo $row['id']; ?>" class="btn btn-icon btn-light text-primary"><i class="fas fa-pen small"></i></a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                                <input type="hidden" name="delete_class" value="1">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-icon btn-light text-danger"><i class="fas fa-trash small"></i></button>
                                            </form>
                                        </td>
                                    <?php elseif ($tab == 'subjects'): ?>
                                        <td class="text-muted">#<?php echo $row['id']; ?></td>
                                        <td class="fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td>
                                            <?php if($row['is_active']): ?>
                                                <span class="badge badge-soft-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-soft-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="?tab=subjects&edit=<?php echo $row['id']; ?>" class="btn btn-icon btn-light text-primary"><i class="fas fa-pen small"></i></a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                                <input type="hidden" name="delete_subject" value="1">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-icon btn-light text-danger"><i class="fas fa-trash small"></i></button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD/EDIT FORM -->
        <div class="col-lg-4">
            <div class="sticky-sidebar">
                <div class="card card-modern border-0">
                    <div class="card-header-modern bg-white">
                        <div>
                        <?php if($editId): ?>
                            <span class="text-warning"><i class="fas fa-pen-square me-2"></i>Edit</span>
                        <?php else: ?>
                            <span class="text-primary"><i class="fas fa-plus-circle me-2"></i>New</span>
                        <?php endif; ?>
                        <?php echo ucfirst(rtrim($tab, 's')); ?>
                        </div>
                        <?php if ($editId): ?>
                            <a href="?tab=<?php echo $tab; ?>" class="btn btn-sm btn-light text-muted"><i class="fas fa-times me-1"></i>Cancel</a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body p-4">
                        
                        <?php if ($tab == 'teachers'): ?>
                        <form method="POST">
                            <?php if ($editId && $editRecord): ?>
                                <input type="hidden" name="edit_teacher" value="1">
                                <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="add_teacher" value="1">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo $editRecord['name'] ?? ''; ?>" placeholder="e.g. John Doe" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Employee Code</label>
                                <input type="text" name="empcode" class="form-control" value="<?php echo $editRecord['empcode'] ?? ''; ?>" placeholder="e.g. EMP001">
                            </div>
                            
                            <!-- Floating Teacher Section Assignment -->
                            <div class="mb-3">
                                <label class="form-label">Department / Section</label>
                                <div class="p-2 border rounded bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <?php 
                                        $currentSections = $editRecord['section_ids'] ?? [];
                                    ?>
                                    <?php foreach($activeSections as $s): ?>
                                        <div class="form-check mb-1">
                                            <input class="form-check-input" type="checkbox" name="section_ids[]" value="<?php echo $s['id']; ?>" 
                                                id="sec_<?php echo $s['id']; ?>"
                                                <?php echo in_array($s['id'], $currentSections) ? 'checked' : ''; ?>>
                                            <label class="form-check-label small" for="sec_<?php echo $s['id']; ?>">
                                                <?php echo htmlspecialchars($s['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text small mt-1"><i class="fas fa-info-circle"></i> If none selected, teacher acts as <strong>Universal Floater</strong>.</div>
                            </div>

                            <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100 py-2 rounded-3 shadow-sm">
                                <i class="fas fa-check me-1"></i> <?php echo $editId ? 'Update Teacher' : 'Create Teacher'; ?>
                            </button>
                        </form>

                        <?php elseif ($tab == 'teacher_subjects'): ?>
                        <!-- This block seems unused in main tabs but kept for safety if referenced -->
                        <!-- ... (same logic as before if needed) ... -->

                        <?php elseif ($tab == 'sections'): ?>
                        <form method="POST">
                            <?php if ($editId && $editRecord): ?>
                                <input type="hidden" name="edit_section" value="1">
                                <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="add_section" value="1">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Section Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo $editRecord['name'] ?? ''; ?>" placeholder="e.g. Primary, Secondary" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Priority Order</label>
                                <input type="number" name="priority" class="form-control" value="<?php echo $editRecord['priority'] ?? 1; ?>" required>
                                <div class="form-text small">Lower numbers appear first.</div>
                            </div>
                            <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100 py-2 rounded-3 shadow-sm">
                                <?php echo $editId ? 'Update Section' : 'Add Section'; ?>
                            </button>
                        </form>

                        <?php elseif ($tab == 'classes'): ?>
                        <form method="POST">
                            <?php if ($editId && $editRecord): ?>
                                <input type="hidden" name="edit_class" value="1">
                                <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="add_class" value="1">
                            <?php endif; ?>
                            <div class="row g-2">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Standard</label>
                                    <input type="text" name="standard" class="form-control" value="<?php echo $editRecord['standard'] ?? ''; ?>" placeholder="10" required>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Division</label>
                                    <input type="text" name="division" class="form-control" value="<?php echo $editRecord['division'] ?? ''; ?>" placeholder="A" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Section</label>
                                <select name="section_id" class="form-select" required>
                                    <?php foreach($activeSections as $s): ?>
                                        <option value="<?php echo $s['id']; ?>" <?php echo ($editRecord && $editRecord['section_id'] == $s['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($s['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100 py-2 rounded-3 shadow-sm">
                                <?php echo $editId ? 'Update Class' : 'Add Class'; ?>
                            </button>
                        </form>

                        <?php elseif ($tab == 'subjects'): ?>
                        <form method="POST">
                            <?php if ($editId && $editRecord): ?>
                                <input type="hidden" name="edit_subject" value="1">
                                <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                            <?php else: ?>
                                <input type="hidden" name="add_subject" value="1">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label class="form-label">Subject Name</label>
                                <input type="text" name="name" class="form-control" value="<?php echo $editRecord['name'] ?? ''; ?>" placeholder="e.g. Mathematics" required>
                            </div>
                            <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100 py-2 rounded-3 shadow-sm">
                                <?php echo $editId ? 'Update Subject' : 'Add Subject'; ?>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Import Teachers from CSV Modal -->
<div class="modal fade" id="importCsvModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-file-csv text-success me-2"></i> Import Teachers from Excel/CSV
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-4">
                    <input type="hidden" name="import_teachers_csv" value="1">
                    
                    <div class="mb-4 text-center">
                        <p class="text-muted mb-3">Upload a CSV file with columns: <strong>Name, EmpCode</strong></p>
                        <a href="download_teacher_template.php" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-download me-1"></i> Download Template
                        </a>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Select File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4 rounded-pill">Upload & Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Teachers from API Modal -->
<div class="modal fade" id="importTeachersModal" tabindex="-1" aria-labelledby="importTeachersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <form method="POST">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="importTeachersModalLabel">
                        <i class="fas fa-cloud-download-alt text-primary me-2"></i> Import Teachers
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-4">
                    <input type="hidden" name="import_teachers_from_api" value="1">
                    
                    <div class="text-center mb-4">
                        <div class="bg-light p-3 rounded-circle d-inline-block mb-3">
                            <i class="fas fa-sync fa-2x text-primary"></i>
                        </div>
                        <h6 class="fw-bold">eTime Office API Sync</h6>
                        <p class="text-muted small">Fetch teacher details directly from your attendance system.</p>
                    </div>

                    <div class="alert alert-info border-0 bg-soft-primary d-flex">
                        <i class="fas fa-info-circle mt-1 me-2 text-primary"></i>
                        <span class="small text-dark">This will scan the last 30 days of attendance data to find active employees and import them as teachers.</span>
                    </div>
                    
                    <div class="form-check mb-3 p-3 border rounded bg-light">
                        <input class="form-check-input" type="checkbox" checked disabled>
                        <label class="form-check-label small ms-1 text-muted">
                            Existing teachers (by Employee Code) will be skipped to prevent duplicates.
                        </label>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill">
                        Start Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
