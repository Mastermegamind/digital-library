<?php
// ====== CONFIG: Minimum values you want ======
$required = [
    'upload_max_filesize' => '100M',
    'post_max_size'       => '120M',
    'memory_limit'        => '256M',
    'max_execution_time'  => '120',    // seconds
    'max_input_time'      => '120',    // seconds
    'max_file_uploads'    => '50',     // files
];

// Convert shorthand like 128M / 2G / 512K to bytes
function toBytes($val) {
    $val = trim($val);
    if ($val == '' || $val == '-1') {
        // -1 means "no limit" in PHP for memory_limit etc.
        return PHP_INT_MAX;
    }

    $last = strtolower($val[strlen($val)-1]);
    $value = (float)$val;

    switch ($last) {
        case 'g':
            $value *= 1024;
            // no break
        case 'm':
            $value *= 1024;
            // no break
        case 'k':
            $value *= 1024;
            break;
    }

    return (int)$value;
}

function renderRow($name, $required) {
    $current = ini_get($name);

    // Some directives are time/count (no M/G/K), handle them differently
    $isSize = preg_match('/[KMG]$/i', $required);

    if ($isSize) {
        $currentBytes  = toBytes($current);
        $requiredBytes = toBytes($required);
        $ok = $currentBytes >= $requiredBytes;
    } else {
        $currentVal  = (int)$current;
        $requiredVal = (int)$required;
        // -1 means "no limit" so always OK
        $ok = ($currentVal == -1) ? true : ($currentVal >= $requiredVal);
    }

    $statusText  = $ok ? 'OK' : 'LOW';
    $statusClass = $ok ? 'ok' : 'low';

    echo "<tr class='{$statusClass}'>
            <td>{$name}</td>
            <td><code>" . htmlspecialchars($current) . "</code></td>
            <td><code>" . htmlspecialchars($required) . "</code></td>
            <td class='status'>{$statusText}</td>
          </tr>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Server Limits Check</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        h1 {
            margin-bottom: 5px;
        }
        small {
            color: #666;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            max-width: 800px;
            background: #fff;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
            font-size: 14px;
        }
        th {
            background: #fafafa;
            font-weight: 600;
        }
        tr.ok .status {
            color: #0f5132;
            font-weight: 600;
        }
        tr.low .status {
            color: #842029;
            font-weight: 600;
        }
        tr.low {
            background: #fff5f5;
        }
        code {
            background: #f0f0f0;
            padding: 2px 4px;
            border-radius: 4px;
        }
        .meta {
            margin-top: 15px;
            max-width: 800px;
            font-size: 13px;
            color: #555;
        }
        .meta code {
            background: #eee;
        }
    </style>
</head>
<body>

<h1>Server Limits Check</h1>
<small>PHP version: <strong><?php echo PHP_VERSION; ?></strong></small>

<table>
    <thead>
        <tr>
            <th>Directive</th>
            <th>Current Value</th>
            <th>Required Minimum</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <?php
        foreach ($required as $name => $min) {
            renderRow($name, $min);
        }
        ?>
    </tbody>
</table>

<div class="meta">
    <p><strong>Note:</strong></p>
    <ul>
        <li><code>upload_max_filesize</code> and <code>post_max_size</code> control how big uploads can be.</li>
        <li><code>memory_limit</code> must be high enough so PHP can process large files.</li>
        <li><code>max_execution_time</code> and <code>max_input_time</code> affect long-running uploads/conversions.</li>
        <li>Web server (Apache/Nginx) may have its own body size limits (e.g., <code>LimitRequestBody</code>, <code>client_max_body_size</code>) not visible here.</li>
    </ul>
</div>

</body>
</html>


<?php phpinfo();?>
