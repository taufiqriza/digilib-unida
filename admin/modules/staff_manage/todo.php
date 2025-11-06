<?php
require_once __DIR__ . '/inc/config.inc.php';

$feedback = null;
$type = 'info';
$redirect = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $sm_can_write) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save_todo') {
        $todoId = !empty($_POST['todo_id']) ? (int)$_POST['todo_id'] : null;
        $payload = [
            'task_title' => $_POST['task_title'] ?? '',
            'task_description' => $_POST['task_description'] ?? '',
            'assigned_to' => $_POST['assigned_to'] ?? null,
            'priority' => $_POST['priority'] ?? 'medium',
            'status' => $_POST['status'] ?? 'pending',
            'progress' => $_POST['progress'] ?? 0,
            'due_date' => $_POST['due_date'] ?? null,
            'is_team_task' => isset($_POST['is_team_task']) ? 1 : 0
        ];
        try {
            if ($sm_service->saveTodo($payload, $todoId)) {
                $feedback = $todoId ? __('Todo berhasil diperbarui.') : __('Todo baru berhasil dibuat.');
                $type = 'success';
                $redirect = true;
            } else {
                $feedback = __('Todo gagal disimpan.');
                $type = 'danger';
            }
        } catch (Throwable $error) {
            $feedback = __('Terjadi kesalahan: ') . $error->getMessage();
            $type = 'danger';
        }
    }
    if ($action === 'update_status' && !empty($_POST['todo_id'])) {
        $id = (int)$_POST['todo_id'];
        $status = $_POST['status'] ?? 'pending';
        $progress = isset($_POST['progress']) ? (int)$_POST['progress'] : 0;
        if ($sm_service->updateTodoStatus($id, $status, $progress)) {
            $feedback = __('Status todo diperbarui.');
            $type = 'success';
            $redirect = true;
        }
    }
    if ($action === 'delete_todo' && !empty($_POST['delete_id'])) {
        if ($sm_service->deleteTodo((int)$_POST['delete_id'])) {
            $feedback = __('Todo berhasil dihapus.');
            $type = 'success';
            $redirect = true;
        }
    }

    if ($redirect) {
        header('Location: ' . MWB . 'staff_manage/todo.php?notice=' . urlencode($feedback) . '&type=' . $type);
        exit;
    }
}

if (!empty($_GET['notice'])) {
    $feedback = $_GET['notice'];
    $type = $_GET['type'] ?? 'info';
}

$filters = [
    'status' => $_GET['status'] ?? 'all',
    'priority' => $_GET['priority'] ?? 'all'
];

$statusOptions = [
    'pending' => __('Belum'),
    'in_progress' => __('Proses'),
    'completed' => __('Selesai')
];

$priorities = [
    'low' => __('Rendah'),
    'medium' => __('Sedang'),
    'high' => __('Tinggi'),
    'critical' => __('Kritis')
];

$todos = $sm_service->getTodos($filters);
$staffList = $sm_service->getStaffList(true);
$editTodo = null;
if (!empty($_GET['edit'])) {
    $editTodo = $sm_service->getTodoById((int)$_GET['edit']);
}

sm_asset_tags($sm_common_css, $sm_common_js);
?>
<div class="sm-page">
    <?php
    sm_render_page_header(
        __('Todo & Manajemen Tugas'),
        __('Kelola pekerjaan individu/tim dengan prioritas dan progress'),
        [
            [
                'label' => __('Buka Statistik'),
                'href' => MWB . 'staff_manage/stats.php',
                'icon' => 'fas fa-chart-line',
                'class' => 'sm-btn-secondary'
            ]
        ]
    );

    if ($feedback) {
        sm_render_alert(htmlspecialchars($feedback), $type);
    }
    ?>

    <div class="sm-panel" id="todo-form">
        <div class="sm-panel-header">
            <h2><?php echo $editTodo ? __('Perbarui Todo') : __('Todo Baru'); ?></h2>
            <span><?php echo __('Gunakan untuk mengkoordinasi tugas individu maupun tim.'); ?></span>
        </div>
        <?php if (!$sm_can_write): ?>
            <?php sm_render_alert(__('Anda tidak memiliki hak untuk memodifikasi todo.'), 'danger'); ?>
        <?php else: ?>
            <form method="post" class="sm-form-grid two">
                <input type="hidden" name="action" value="save_todo">
                <input type="hidden" name="todo_id" value="<?php echo htmlspecialchars($editTodo['id'] ?? ''); ?>">

                <div class="two-column-full">
                    <label class="sm-form-label" for="task_title"><?php echo __('Judul Tugas'); ?></label>
                    <input type="text" class="sm-input" id="task_title" name="task_title" value="<?php echo htmlspecialchars($editTodo['task_title'] ?? ''); ?>" required>
                </div>

                <div class="two-column-full">
                    <label class="sm-form-label" for="task_description"><?php echo __('Deskripsi'); ?></label>
                    <textarea class="sm-textarea" id="task_description" name="task_description"><?php echo htmlspecialchars($editTodo['task_description'] ?? ''); ?></textarea>
                </div>

                <div>
                    <label class="sm-form-label" for="assigned_to"><?php echo __('Penanggung Jawab'); ?></label>
                    <select class="sm-select" id="assigned_to" name="assigned_to">
                        <option value="">-- <?php echo __('Tim/Umum'); ?> --</option>
                        <?php foreach ($staffList as $staff): ?>
                            <option value="<?php echo (int)$staff['id']; ?>" <?php echo (!empty($editTodo['assigned_to']) && (int)$editTodo['assigned_to'] === (int)$staff['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($staff['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="sm-form-label" for="priority"><?php echo __('Prioritas'); ?></label>
                    <select class="sm-select" id="priority" name="priority">
                        <?php foreach ($priorities as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo (!empty($editTodo['priority']) && $editTodo['priority'] === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="sm-form-label" for="status"><?php echo __('Status'); ?></label>
                    <select class="sm-select" id="status" name="status">
                        <?php foreach ($statusOptions as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo (!empty($editTodo['status']) && $editTodo['status'] === $key) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="sm-form-label" for="due_date"><?php echo __('Jatuh Tempo'); ?></label>
                    <input type="date" class="sm-input" id="due_date" name="due_date" value="<?php echo htmlspecialchars($editTodo['due_date'] ?? ''); ?>">
                </div>

                <div>
                    <label class="sm-form-label" for="progress"><?php echo __('Progress'); ?></label>
                    <input type="range" min="0" max="100" step="5" id="progress" name="progress" data-sm-progress="#progress-value" value="<?php echo (int)($editTodo['progress'] ?? 0); ?>">
                    <span class="sm-card-meta" id="progress-value"></span>
                </div>

                <div>
                    <label class="sm-form-label" for="is_team_task"><?php echo __('Tugas Tim'); ?></label>
                    <label style="display:flex;align-items:center;gap:0.5rem;">
                        <input type="checkbox" id="is_team_task" name="is_team_task" <?php echo !empty($editTodo['is_team_task']) ? 'checked' : ''; ?>>
                        <span><?php echo __('Tandai sebagai tugas kolaboratif'); ?></span>
                    </label>
                </div>

                <div class="sm-actions">
                    <button type="submit" class="sm-btn"><i class="fas fa-save"></i> <span><?php echo $editTodo ? __('Perbarui Todo') : __('Simpan Todo'); ?></span></button>
                    <?php if ($editTodo): ?>
                        <a class="sm-btn sm-btn-secondary" href="<?php echo MWB . 'staff_manage/todo.php'; ?>">
                            <i class="fas fa-undo"></i>
                            <span><?php echo __('Batal'); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div class="sm-panel">
        <div class="sm-panel-header">
            <h2><?php echo __('Filter Todo'); ?></h2>
            <span><?php echo __('Gunakan prioritas & status untuk fokus pada pekerjaan penting.'); ?></span>
        </div>
        <form method="get" class="sm-filter-bar">
            <div>
                <label class="sm-form-label" for="status_filter"><?php echo __('Status'); ?></label>
                <select class="sm-select" id="status_filter" name="status">
                    <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>><?php echo __('Semua'); ?></option>
                    <?php foreach ($statusOptions as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="sm-form-label" for="priority_filter"><?php echo __('Prioritas'); ?></label>
                <select class="sm-select" id="priority_filter" name="priority">
                    <option value="all" <?php echo $filters['priority'] === 'all' ? 'selected' : ''; ?>><?php echo __('Semua'); ?></option>
                    <?php foreach ($priorities as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $filters['priority'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="sm-form-label" style="visibility:hidden;">&nbsp;</label>
                <button type="submit"><i class="fas fa-filter"></i> <?php echo __('Terapkan'); ?></button>
            </div>
        </form>
    </div>

    <div class="sm-panel">
        <div class="sm-panel-header">
            <h2><?php echo __('Daftar Todo'); ?></h2>
            <span><?php echo __('Kelompokkan otomatis berdasarkan status.'); ?></span>
        </div>
        <?php if ($todos): ?>
            <div class="sm-kanban">
                <?php
                $columns = ['pending' => [], 'in_progress' => [], 'completed' => []];
                foreach ($todos as $todo) {
                    $columns[$todo['status']][] = $todo;
                }
                foreach ($columns as $status => $items): ?>
                    <div class="sm-kanban-column">
                        <h3><?php echo $statusOptions[$status]; ?> <?php echo sm_badge((string)count($items), $status === 'completed' ? 'success' : 'default'); ?></h3>
                        <?php if ($items): ?>
                            <?php foreach ($items as $row): ?>
                                <div class="sm-task-card">
                                    <h4><?php echo htmlspecialchars($row['task_title']); ?></h4>
                                    <p class="sm-card-meta"><?php echo htmlspecialchars($row['task_description'] ?? ''); ?></p>
                                    <div class="sm-card-meta">
                                        <i class="fas fa-flag"></i>
                                        <?php echo sm_badge($priorities[$row['priority']], $row['priority'] === 'critical' ? 'danger' : ($row['priority'] === 'high' ? 'warning' : 'default')); ?>
                                    </div>
                                    <?php if (!empty($row['full_name'])): ?>
                                        <div class="sm-card-meta"><i class="fas fa-user"></i> <?php echo htmlspecialchars($row['full_name']); ?></div>
                                    <?php else: ?>
                                        <div class="sm-chip"><i class="fas fa-users"></i> <?php echo __('Tugas Tim'); ?></div>
                                    <?php endif; ?>
                                    <div class="sm-progress"><span style="width: <?php echo (int)$row['progress']; ?>%"></span></div>
                                    <div class="sm-card-meta">
                                        <i class="fas fa-calendar"></i> <?php echo sm_format_date($row['due_date']); ?>
                                    </div>
                                    <div class="sm-actions" style="margin-top:1rem;">
                                        <a class="sm-btn sm-btn-secondary" href="<?php echo MWB . 'staff_manage/todo.php?edit=' . (int)$row['id']; ?>#todo-form"><i class="fas fa-edit"></i></a>
                                        <?php if ($sm_can_write): ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('<?php echo __('Hapus todo ini?'); ?>');">
                                                <input type="hidden" name="action" value="delete_todo">
                                                <input type="hidden" name="delete_id" value="<?php echo (int)$row['id']; ?>">
                                                <button type="submit" class="sm-btn sm-btn-secondary" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="post" style="display:inline-flex;gap:0.4rem;align-items:center;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="todo_id" value="<?php echo (int)$row['id']; ?>">
                                            <select name="status" class="sm-select">
                                                <?php foreach ($statusOptions as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>" <?php echo $row['status'] === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="number" name="progress" class="sm-input" style="width:80px;" min="0" max="100" step="5" value="<?php echo (int)$row['progress']; ?>">
                                            <button type="submit" class="sm-btn sm-btn-secondary"><i class="fas fa-sync"></i></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php sm_empty_state(__('Tidak ada todo pada status ini.')); ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php sm_empty_state(__('Belum ada todo yang terdaftar.')); ?>
        <?php endif; ?>
    </div>
</div>
