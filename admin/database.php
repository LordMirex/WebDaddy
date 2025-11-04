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

// Get all tables (SQLite version)
$tables = $db->query("
    SELECT name 
    FROM sqlite_master 
    WHERE type = 'table' 
    AND name NOT LIKE 'sqlite_%'
    ORDER BY name
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

<div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
        <i class="bi bi-database text-primary-600"></i> Database Viewer
    </h1>
    <p class="text-gray-600 mt-2">View all database tables and records</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Tables List -->
    <div>
        <div class="bg-white rounded-xl shadow-md border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-200">
                <h5 class="text-lg font-bold text-gray-900">Tables (<?php echo count($tables); ?>)</h5>
            </div>
            <div class="divide-y divide-gray-200">
                <?php foreach ($tables as $table): ?>
                    <?php 
                    $count = $db->query("SELECT COUNT(*) FROM \"$table\"")->fetchColumn();
                    $isActive = ($selectedTable === $table);
                    ?>
                    <a href="?table=<?php echo urlencode($table); ?>" 
                       class="flex justify-between items-center px-6 py-3 hover:bg-gray-50 transition-colors <?php echo $isActive ? 'bg-primary-50 border-l-4 border-primary-600' : ''; ?>">
                        <span class="flex items-center gap-2 <?php echo $isActive ? 'text-primary-700 font-semibold' : 'text-gray-700'; ?>">
                            <i class="bi bi-table"></i> <?php echo htmlspecialchars($table); ?>
                        </span>
                        <span class="px-2 py-1 bg-primary-100 text-primary-800 rounded-full text-xs font-semibold"><?php echo $count; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Table Data -->
    <div class="md:col-span-3">
        <?php if ($selectedTable): ?>
            <div class="bg-white rounded-xl shadow-md border border-gray-100">
                <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200">
                    <h5 class="text-xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="bi bi-table text-primary-600"></i> <?php echo htmlspecialchars($selectedTable); ?>
                        <span class="px-3 py-1 bg-gray-200 text-gray-800 rounded-full text-sm font-semibold"><?php echo $rowCount; ?> rows</span>
                    </h5>
                    <button class="px-4 py-2 border border-primary-600 text-primary-600 hover:bg-primary-50 rounded-lg font-medium transition-colors text-sm" onclick="exportTableData()">
                        <i class="bi bi-download"></i> Export JSON
                    </button>
                </div>
                <div class="p-6">
                    <?php if (!empty($tableData)): ?>
                        <div class="overflow-x-auto">
                            <table class="w-full" id="dataTable">
                                <thead>
                                    <tr class="border-b-2 border-gray-300">
                                        <?php foreach (array_keys($tableData[0]) as $column): ?>
                                            <th class="text-left py-3 px-4 font-semibold text-gray-700 text-sm"><?php echo htmlspecialchars($column); ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($tableData as $row): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <?php foreach ($row as $value): ?>
                                                <td class="py-3 px-4 text-sm text-gray-700">
                                                    <?php 
                                                    if (is_bool($value)) {
                                                        echo $value ? '<span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">true</span>' : '<span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold">false</span>';
                                                    } elseif (is_null($value)) {
                                                        echo '<span class="text-gray-400 italic">NULL</span>';
                                                    } elseif (strlen($value) > 100) {
                                                        echo '<span class="cursor-help" title="' . htmlspecialchars($value) . '">' . htmlspecialchars(substr($value, 0, 100)) . '...</span>';
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
                        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded-lg flex items-center gap-3">
                            <i class="bi bi-info-circle text-xl"></i>
                            <span>No data in this table.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md border border-gray-100">
                <div class="p-12 text-center">
                    <i class="bi bi-table text-8xl text-gray-300"></i>
                    <h4 class="mt-4 text-2xl font-bold text-gray-700">Select a Table</h4>
                    <p class="text-gray-500 mt-2">Choose a table from the left to view its data</p>
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
