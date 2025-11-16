<?php
/**
 * Upload Diagnostic Page
 * Shows current PHP upload configuration and tests directory permissions
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

function convertToBytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    return $val;
}

$uploadMaxFilesize = ini_get('upload_max_filesize');
$postMaxSize = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');
$maxInputTime = ini_get('max_input_time');

$uploadMaxBytes = convertToBytes($uploadMaxFilesize);
$postMaxBytes = convertToBytes($postMaxSize);

$uploadDirChecks = [
    'uploads/' => UPLOAD_DIR,
    'uploads/templates/' => UPLOAD_DIR . '/templates',
    'uploads/templates/images/' => UPLOAD_DIR . '/templates/images',
    'uploads/templates/videos/' => UPLOAD_DIR . '/templates/videos',
    'uploads/tools/' => UPLOAD_DIR . '/tools',
    'uploads/tools/images/' => UPLOAD_DIR . '/tools/images',
    'uploads/tools/videos/' => UPLOAD_DIR . '/tools/videos',
    'uploads/temp/' => UPLOAD_DIR . '/temp'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Diagnostic - WebDaddy Empire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container my-5">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1>Upload Diagnostic</h1>
                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">PHP Upload Configuration</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">Setting</th>
                                <th width="20%">Current Value</th>
                                <th width="20%">Required Value</th>
                                <th width="30%">Status</th>
                            </tr>
                            <tr>
                                <td><strong>upload_max_filesize</strong></td>
                                <td><?php echo $uploadMaxFilesize; ?> (<?php echo formatBytes($uploadMaxBytes); ?>)</td>
                                <td>120M</td>
                                <td>
                                    <?php if ($uploadMaxBytes >= 120 * 1024 * 1024): ?>
                                        <span class="badge bg-success">✓ OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ TOO LOW</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>post_max_size</strong></td>
                                <td><?php echo $postMaxSize; ?> (<?php echo formatBytes($postMaxBytes); ?>)</td>
                                <td>130M</td>
                                <td>
                                    <?php if ($postMaxBytes >= 130 * 1024 * 1024): ?>
                                        <span class="badge bg-success">✓ OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ TOO LOW</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>memory_limit</strong></td>
                                <td><?php echo $memoryLimit; ?></td>
                                <td>256M</td>
                                <td>
                                    <?php 
                                    $memBytes = convertToBytes($memoryLimit);
                                    if ($memBytes >= 256 * 1024 * 1024 || $memoryLimit == '-1'): ?>
                                        <span class="badge bg-success">✓ OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">⚠ LOW</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>max_execution_time</strong></td>
                                <td><?php echo $maxExecutionTime; ?>s</td>
                                <td>300s</td>
                                <td>
                                    <?php if ($maxExecutionTime >= 300 || $maxExecutionTime == 0): ?>
                                        <span class="badge bg-success">✓ OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">⚠ LOW</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                        
                        <?php if ($uploadMaxBytes < 120 * 1024 * 1024 || $postMaxBytes < 130 * 1024 * 1024): ?>
                        <div class="alert alert-danger mt-3">
                            <strong>Upload limits are too low!</strong><br>
                            The PHP configuration needs to be updated. Please check:
                            <ul class="mb-0">
                                <li>.htaccess file (mod_php settings)</li>
                                <li>.user.ini file in root directory</li>
                                <li>php.ini configuration file</li>
                            </ul>
                            You may need to contact your hosting provider or restart PHP-FPM.
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success mt-3">
                            <strong>PHP upload limits are properly configured!</strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Directory Permissions Check</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="30%">Directory</th>
                                <th width="20%">Exists</th>
                                <th width="20%">Writable</th>
                                <th width="30%">Permissions</th>
                            </tr>
                            <?php foreach ($uploadDirChecks as $label => $path): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($label); ?></td>
                                <td>
                                    <?php if (is_dir($path)): ?>
                                        <span class="badge bg-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (is_dir($path) && is_writable($path)): ?>
                                        <span class="badge bg-success">✓ Yes</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    if (is_dir($path)) {
                                        echo substr(sprintf('%o', fileperms($path)), -4);
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Application Configuration</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tr>
                                <th width="40%">Setting</th>
                                <th width="60%">Value</th>
                            </tr>
                            <tr>
                                <td><strong>MAX_IMAGE_SIZE</strong></td>
                                <td><?php echo formatBytes(MAX_IMAGE_SIZE); ?></td>
                            </tr>
                            <tr>
                                <td><strong>MAX_VIDEO_SIZE</strong></td>
                                <td><?php echo formatBytes(MAX_VIDEO_SIZE); ?></td>
                            </tr>
                            <tr>
                                <td><strong>ALLOWED_IMAGE_EXTENSIONS</strong></td>
                                <td><?php echo implode(', ', ALLOWED_IMAGE_EXTENSIONS); ?></td>
                            </tr>
                            <tr>
                                <td><strong>ALLOWED_VIDEO_EXTENSIONS</strong></td>
                                <td><?php echo implode(', ', ALLOWED_VIDEO_EXTENSIONS); ?></td>
                            </tr>
                            <tr>
                                <td><strong>FFmpeg Available</strong></td>
                                <td>
                                    <?php 
                                    exec('which ffmpeg 2>&1', $ffmpegOutput, $ffmpegStatus);
                                    if ($ffmpegStatus === 0 && !empty($ffmpegOutput[0])): ?>
                                        <span class="badge bg-success">✓ Available</span> (<?php echo htmlspecialchars($ffmpegOutput[0]); ?>)
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ Not Found</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>FFprobe Available</strong></td>
                                <td>
                                    <?php 
                                    exec('which ffprobe 2>&1', $ffprobeOutput, $ffprobeStatus);
                                    if ($ffprobeStatus === 0 && !empty($ffprobeOutput[0])): ?>
                                        <span class="badge bg-success">✓ Available</span> (<?php echo htmlspecialchars($ffprobeOutput[0]); ?>)
                                    <?php else: ?>
                                        <span class="badge bg-danger">✗ Not Found</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header bg-warning">
                        <h5 class="mb-0">Action Required</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $issues = [];
                        
                        if ($uploadMaxBytes < 120 * 1024 * 1024) {
                            $issues[] = "PHP upload_max_filesize is too low (currently {$uploadMaxFilesize}, needs to be at least 120M)";
                        }
                        
                        if ($postMaxBytes < 130 * 1024 * 1024) {
                            $issues[] = "PHP post_max_size is too low (currently {$postMaxSize}, needs to be at least 130M)";
                        }
                        
                        foreach ($uploadDirChecks as $label => $path) {
                            if (!is_dir($path)) {
                                $issues[] = "Directory does not exist: {$label}";
                            } elseif (!is_writable($path)) {
                                $issues[] = "Directory is not writable: {$label}";
                            }
                        }
                        
                        if (empty($issues)): ?>
                            <div class="alert alert-success">
                                <strong>✓ All checks passed!</strong><br>
                                Your upload system is properly configured and should work correctly.
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <strong>Issues found:</strong>
                                <ol class="mb-0">
                                    <?php foreach ($issues as $issue): ?>
                                        <li><?php echo htmlspecialchars($issue); ?></li>
                                    <?php endforeach; ?>
                                </ol>
                            </div>
                            <div class="alert alert-info mt-3">
                                <strong>Recommended fixes:</strong>
                                <ul class="mb-0">
                                    <li>Run: <code>chmod -R 775 uploads/</code> to fix directory permissions</li>
                                    <li>Check if .htaccess and .user.ini files are in the root directory</li>
                                    <li>Restart your web server or PHP-FPM to apply changes</li>
                                    <li>If on Replit, the changes should apply automatically after a few seconds</li>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
