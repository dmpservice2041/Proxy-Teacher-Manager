<?php
require_once 'config/app.php';
require_once 'includes/header.php';
require_once 'models/Teacher.php';
require_once 'models/Section.php';
require_once 'models/Classes.php';
require_once 'models/Subject.php';
require_once 'models/Settings.php';
require_once 'services/ETimeService.php';

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
        // SETTINGS
        elseif (isset($_POST['update_settings'])) {
            $settingsModel->set('total_periods', $_POST['total_periods']);
            $settingsModel->set('max_daily_proxy', $_POST['max_daily_proxy']);
            $settingsModel->set('max_weekly_proxy', $_POST['max_weekly_proxy']);
            $settingsModel->set('school_name', $_POST['school_name']);
            $message = "Settings updated successfully.";
        }
        
    } catch (PDOException $e) {

        $error = "Error: " . $e->getMessage();
    }
}

// --- Fetch Data for Display ---
$activeSections = $sectionModel->getAllOrderedByPriority();

if ($tab == 'teachers') {
    // Validate sort column
    $allowedSortColumns = ['id' => 't.id', 'name' => 't.name', 'empcode' => 't.empcode'];
    $sortColumn = $allowedSortColumns[$sortBy] ?? 't.id';
    $sortDirection = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
    
    // Fetch teachers with sorting
    $stmt = $pdo->query("
        SELECT t.*, GROUP_CONCAT(s.name SEPARATOR ', ') as section_names
        FROM teachers t
        LEFT JOIN teacher_sections ts ON t.id = ts.teacher_id
        LEFT JOIN sections s ON ts.section_id = s.id
        GROUP BY t.id
        ORDER BY {$sortColumn} {$sortDirection}
    ");
    $data = $stmt->fetchAll();
    $headers = ['ID', 'Name', 'Emp Code', 'Department', 'Active', 'Actions'];
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
    $headers = ['ID', 'Name', 'Active', 'Actions'];
    $editRecord = $editId ? $subjectModel->find($editId) : null;
}
?>

<div class="container-fluid mt-4">
    <h2>Master Data Management</h2>
    
    <?php if ($message): ?><div class="alert alert-success alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?php echo $message; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show"><button type="button" class="btn-close" data-bs-dismiss="alert"></button><?php echo $error; ?></div><?php endif; ?>

    <ul class="nav nav-tabs mb-4">
        <li class="nav-item"><a class="nav-link <?php echo $tab == 'teachers' ? 'active' : ''; ?>" href="?tab=teachers">Teachers</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab == 'sections' ? 'active' : ''; ?>" href="?tab=sections">Sections</a></li>
        <li class="nav-item"><a class="nav-link <?php echo $tab == 'classes' ? 'active' : ''; ?>" href="?tab=classes">Classes</a></li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'subjects' ? 'active' : ''; ?>" href="?tab=subjects">Subjects</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'settings' ? 'active' : ''; ?>" href="?tab=settings">Settings</a>
        </li>
    </ul>

    </ul>

    <?php if ($tab === 'settings'): 
            $currentPeriods = $settingsModel->get('total_periods', 8); 
            $maxDaily = $settingsModel->get('max_daily_proxy', 2); 
            $maxWeekly = $settingsModel->get('max_weekly_proxy', 10); 
            $schoolName = $settingsModel->get('school_name', defined('SCHOOL_NAME') ? SCHOOL_NAME : 'Our School');
        ?>
        <div class="row mt-4">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <i class="fas fa-cogs"></i> System Configuration
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="update_settings" value="1">
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">School Name</label>
                                <input type="text" name="school_name" class="form-control" value="<?php echo htmlspecialchars($schoolName); ?>" required>
                                <div class="form-text">This name will appear on reports and headers.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Total Periods in Timetable</label>
                                <input type="number" name="total_periods" class="form-control" value="<?php echo htmlspecialchars($currentPeriods); ?>" min="1" max="15" required>
                                <div class="form-text">Set the maximum number of periods displayed in the timetable.</div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">Max Proxies Per Day</label>
                                    <input type="number" name="max_daily_proxy" class="form-control" value="<?php echo htmlspecialchars($maxDaily); ?>" min="0" max="8" required>
                                    <div class="form-text">Limit per teacher.</div>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">Max Proxies Per Week</label>
                                    <input type="number" name="max_weekly_proxy" class="form-control" value="<?php echo htmlspecialchars($maxWeekly); ?>" min="0" max="40" required>
                                    <div class="form-text">Limit per teacher.</div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>

    <div class="row">
        <!-- LIST VIEW -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><?php echo ucfirst($tab); ?> List</h5>
                    <?php if ($tab == 'teachers'): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#importTeachersModal">
                            <i class="fas fa-cloud-download-alt"></i> Import from API
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm">
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
                                            $icon = '';
                                            if ($sortBy === $column) {
                                                $icon = $sortOrder === 'asc' ? ' <i class="fas fa-sort-up"></i>' : ' <i class="fas fa-sort-down"></i>';
                                            }
                                            ?>
                                            <th>
                                                <a href="?tab=teachers&sort=<?php echo $column; ?>&order=<?php echo $newOrder; ?>" 
                                                   class="text-decoration-none text-dark">
                                                    <?php echo $h . $icon; ?>
                                                </a>
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
                            <?php foreach($data as $row): ?>
                            <tr>
                                <?php if ($tab == 'teachers'): ?>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['empcode'] ?? '-'); ?></td>
                                    <td><small class="text-muted" style="white-space: normal; display: block; max-width: 250px;"><?php echo htmlspecialchars($row['section_names'] ?? '-'); ?></small></td>
                                    <td><?php echo $row['is_active'] ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <a href="?tab=teachers&edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="toggle_teacher_active" value="1">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-<?php echo $row['is_active'] ? 'secondary' : 'success'; ?>">
                                                <?php echo $row['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this teacher? This will fail if teacher has historical records.');">
                                            <input type="hidden" name="delete_teacher" value="1">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                <?php elseif ($tab == 'teacher_subjects'): ?>
                                    <td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this assignment?');">
                                            <input type="hidden" name="delete_teacher_subject" value="1">
                                            <input type="hidden" name="teacher_id" value="<?php echo $row['teacher_id']; ?>">
                                            <input type="hidden" name="subject_id" value="<?php echo $row['subject_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                                        </form>
                                    </td>
                                <?php elseif ($tab == 'sections'): ?>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo $row['priority']; ?></td>
                                    <td>
                                        <a href="?tab=sections&edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="delete_section" value="1">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                <?php elseif ($tab == 'classes'): ?>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['standard']); ?></td>
                                    <td><?php echo htmlspecialchars($row['division']); ?></td>
                                    <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                                    <td>
                                        <a href="?tab=classes&edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="delete_class" value="1">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                <?php elseif ($tab == 'subjects'): ?>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo $row['is_active'] ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <a href="?tab=subjects&edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?');">
                                            <input type="hidden" name="delete_subject" value="1">
                                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ADD/EDIT FORM -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-<?php echo $editId ? 'warning' : 'primary'; ?> text-white">
                    <h5 class="mb-0"><?php echo $editId ? 'Edit' : 'Add New'; ?> <?php echo ucfirst(rtrim($tab, 's')); ?></h5>
                    <?php if ($editId): ?><a href="?tab=<?php echo $tab; ?>" class="btn btn-sm btn-light float-end">Cancel</a><?php endif; ?>
                </div>
                <div class="card-body">
                    
                    <?php if ($tab == 'teachers'): ?>
                    <form method="POST">
                        <?php if ($editId && $editRecord): ?>
                            <input type="hidden" name="edit_teacher" value="1">
                            <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="add_teacher" value="1">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo $editRecord['name'] ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label>Emp Code</label>
                            <input type="text" name="empcode" class="form-control" value="<?php echo $editRecord['empcode'] ?? ''; ?>">
                        </div>
                        
                        <!-- Floating Teacher Section Assignment -->
                        <div class="mb-3">
                            <label>Assigned Department / Section (Optional)</label>
                            <select name="section_ids[]" class="form-select" multiple size="4">
                                <option value="" disabled>-- Select Departments (Hold Cmd/Ctrl to select multiple) --</option>
                                <?php 
                                    $currentSections = $editRecord['section_ids'] ?? [];
                                ?>
                                <?php foreach($activeSections as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo in_array($s['id'], $currentSections) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select multiple if needed. If none selected, acts as Universal Floater.</div>
                        </div>

                        <?php if ($editId): ?>
                        <div class="mb-3">
                            <label>Employee Code <small class="text-muted">(for API sync)</small></label>
                            <input type="text" name="empcode" class="form-control" value="<?php echo $editRecord['empcode'] ?? ''; ?>" placeholder="e.g., 0001">
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100">
                            <?php echo $editId ? 'Update' : 'Add'; ?> Teacher
                        </button>
                    </form>

                    <?php elseif ($tab == 'teacher_subjects'): ?>
                    <form method="POST">
                        <input type="hidden" name="add_teacher_subject" value="1">
                        <div class="mb-3">
                            <label class="fw-bold">Teacher</label>
                            <select name="teacher_id" id="teacherSelect" class="form-select" required>
                                <option value="">-- Select Teacher --</option>
                                <?php foreach($teacherModel->getAllActive() as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="fw-bold">Subjects (Select Multiple)</label>
                            <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto;">
                                <?php foreach($subjectModel->getAll() as $s): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="subject_ids[]" value="<?php echo $s['id']; ?>" id="subj<?php echo $s['id']; ?>">
                                        <label class="form-check-label" for="subj<?php echo $s['id']; ?>">
                                            <?php echo htmlspecialchars($s['name']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted">Check multiple subjects to assign at once</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i> Assign Selected Subjects
                        </button>
                    </form>

                    <?php elseif ($tab == 'sections'): ?>
                    <form method="POST">
                        <?php if ($editId && $editRecord): ?>
                            <input type="hidden" name="edit_section" value="1">
                            <input type="hidden" name="id" value="<?php echo $editRecord['id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="add_section" value="1">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo $editRecord['name'] ?? ''; ?>" placeholder="e.g. Primary" required>
                        </div>
                        <div class="mb-3">
                            <label>Priority (1=High)</label>
                            <input type="number" name="priority" class="form-control" value="<?php echo $editRecord['priority'] ?? 1; ?>" required>
                        </div>
                        <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100">
                            <?php echo $editId ? 'Update' : 'Add'; ?> Section
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
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label>Standard</label>
                                <input type="text" name="standard" class="form-control" value="<?php echo $editRecord['standard'] ?? ''; ?>" placeholder="10" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label>Division</label>
                                <input type="text" name="division" class="form-control" value="<?php echo $editRecord['division'] ?? ''; ?>" placeholder="A" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label>Section</label>
                            <select name="section_id" class="form-select" required>
                                <?php foreach($activeSections as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" <?php echo ($editRecord && $editRecord['section_id'] == $s['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100">
                            <?php echo $editId ? 'Update' : 'Add'; ?> Class
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
                            <label>Subject Name</label>
                            <input type="text" name="name" class="form-control" value="<?php echo $editRecord['name'] ?? ''; ?>" required>
                        </div>
                        <button type="submit" class="btn btn-<?php echo $editId ? 'warning' : 'primary'; ?> w-100">
                            <?php echo $editId ? 'Update' : 'Add'; ?> Subject
                        </button>
                    </form>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Import Teachers from API Modal -->
<div class="modal fade" id="importTeachersModal" tabindex="-1" aria-labelledby="importTeachersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="importTeachersModalLabel">
                        <i class="fas fa-cloud-download-alt"></i> Import Teachers from eTime Office API
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="import_teachers_from_api" value="1">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> This will fetch teacher names and employee codes from the API (last 30 days of attendance data) and create teacher records automatically. Only active employees will be imported.
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Note:</strong> Teachers with existing employee codes in the database will be skipped (not duplicated).
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Import Teachers
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
