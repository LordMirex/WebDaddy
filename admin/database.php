<?php
$pageTitle = 'Database Viewer';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get all tables
$tables = $db->query("
    SELECT table_name 
    FROM information_schema.tables 
    WHERE table_schema = 'public' 
    AND table_type = 'BASE TABLE'
    ORDER BY table_name
")->fetchAll(PDO::FETCH_COLUMN);

// Get selected table data
$selectedTable = $_GET['table'] ?? '';
$tableData = [];
$rowCount = 0;

if ($selectedTable && in_array($selectedTable, $tables)) {
    $rowCount = $db->query("SELECT COUNT(*) FROM \"$selectedTable\"")->fetchColumn();
    $tableData = $db->query("SELECT * FROM \"$selectedTable\" ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <h1><i class="bi bi-database"></i> Database Viewer</h1>
    <p class="text-muted">View all database tables and records</p>
</div>

<div class="row">
    <!-- Tables List -->
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Tables (<?php echo count($tables); ?>)</h5>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($tables as $table): ?>
                    <?php 
                    $count = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
                    $isActive = ($selectedTable === $table) ? 'active' : '';
                    ?>
                    <a href="?table=<?php echo urlencode($table); ?>" 
                       class="list-group-item list-group-item-action <?php echo $isActive; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-table"></i> <?php echo htmlspecialchars($table); ?></span>
                            <span class="badge bg-primary rounded-pill"><?php echo $count; ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Table Data -->
    <div class="col-md-9">
        <?php if ($selectedTable): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-table"></i> <?php echo htmlspecialchars($selectedTable); ?>
                        <span class="badge bg-secondary"><?php echo $rowCount; ?> rows</span>
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportTableData()">
                        <i class="bi bi-download"></i> Export JSON
                    </button>
                </div>
                <div class="card-body">
                    <?php if (!empty($tableData)): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="dataTable">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($tableData[0]) as $column): ?>
                                            <th><?php echo htmlspecialchars($column); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tableData as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $value): ?>
                                                <td>
                                                    <?php 
                                                    if (is_bool($value)) {
                                                        echo $value ? '<span class="badge bg-success">true</span>' : '<span class="badge bg-danger">false</span>';
                                                    } elseif (is_null($value)) {
                                                        echo '<span class="text-muted">NULL</span>';
                                                    } elseif (strlen($value) > 100) {
                                                        echo '<span title="' . htmlspecialchars($value) . '">' . htmlspecialchars(substr($value, 0, 100)) . '...</span>';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> No data in this table.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-table" style="font-size: 4rem; color: #ccc;"></i>
                    <h4 class="mt-3">Select a Table</h4>
                    <p class="text-muted">Choose a table from the left to view its data</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportTableData() {
    const table = <?php echo json_encode($tableData); ?>;
    const dataStr = JSON.stringify(table, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    const url = URL.createObjectURL(dataBlob);
    const link = document.createElement('a');
    link.href = url;
    link.download = '<?php echo $selectedTable; ?>_data.json';
    link.click();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
