<?php
/**
 * Remove today's pipeline demo PRs, department User accounts, and activity log.
 * Administrators are kept.
 *
 * CLI: C:\xampp\php\php.exe scripts\reset-dept-demo-accounts.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
}

function pipelineDemoRemoveLocal(PDO $conn): array
{
    $conn->exec('SET FOREIGN_KEY_CHECKS=0');
    $deleted = ['quotes' => 0, 'lines' => 0, 'prs' => 0];
    try {
        $conn->exec(
            "DELETE ql FROM proc_quotation_line ql
             INNER JOIN proc_quotation q ON q.id = ql.quotation_id
             INNER JOIN proc_pr p ON p.id = q.pr_id
             WHERE p.pr_number LIKE 'PIPE-2026-%'
                OR p.purpose LIKE '%[PIPELINE_DEMO]%'"
        );
        $deleted['quotes'] = (int) $conn->exec(
            "DELETE q FROM proc_quotation q
             INNER JOIN proc_pr p ON p.id = q.pr_id
             WHERE p.pr_number LIKE 'PIPE-2026-%'
                OR p.purpose LIKE '%[PIPELINE_DEMO]%'"
        );
    } catch (Throwable $e) {
        // optional
    }
    $deleted['lines'] = (int) $conn->exec(
        "DELETE l FROM proc_pr_line l
         INNER JOIN proc_pr p ON p.id = l.pr_id
         WHERE p.pr_number LIKE 'PIPE-2026-%'
            OR p.purpose LIKE '%[PIPELINE_DEMO]%'"
    );
    $deleted['prs'] = (int) $conn->exec(
        "DELETE FROM proc_pr
         WHERE pr_number LIKE 'PIPE-2026-%'
            OR purpose LIKE '%[PIPELINE_DEMO]%'"
    );
    $conn->exec('SET FOREIGN_KEY_CHECKS=1');

    return $deleted;
}

try {
    $conn = getDBConnection();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $nAdmin = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'Admin'")->fetchColumn();
    if ($nAdmin < 1) {
        throw new RuntimeException('No Admin account exists. Aborting so you are not locked out.');
    }

    $pipeNotif = 0;
    try {
        $pipeNotif = (int) $conn->exec(
            "DELETE n FROM notifications n
             LEFT JOIN proc_pr p ON p.id = n.related_id AND n.related_type IN ('proc_pr','pr')
             WHERE (n.related_type IN ('proc_pr','pr') AND (p.pr_number LIKE 'PIPE-2026-%' OR p.purpose LIKE '%[PIPELINE_DEMO]%'))
                OR n.message LIKE '%PIPE-2026-%'
                OR n.message LIKE '%[PIPELINE_DEMO]%'"
        );
    } catch (Throwable $e) {
        $pipeNotif = 0;
    }

    $removedPr = pipelineDemoRemoveLocal($conn);

    $activity = (int) $conn->exec('DELETE FROM user_activity');

    $deptUsers = $conn->query(
        "SELECT id FROM users WHERE COALESCE(TRIM(role), '') <> 'Admin'"
    )->fetchAll(PDO::FETCH_COLUMN);
    $deptIds = array_map('intval', $deptUsers);
    $nDept = count($deptIds);

    if ($deptIds) {
        $in = implode(',', $deptIds);
        foreach (['user_settings', 'notification_reads', 'password_reset_codes', 'registration_codes'] as $tbl) {
            try {
                $conn->exec("DELETE FROM {$tbl} WHERE user_id IN ({$in})");
            } catch (Throwable $e) {
                // table may not exist
            }
        }
        $conn->exec("DELETE FROM users WHERE COALESCE(TRIM(role), '') <> 'Admin'");
    }

    echo "Removed pipeline PRs: {$removedPr['prs']} (lines {$removedPr['lines']}, quotes {$removedPr['quotes']})." . PHP_EOL;
    echo "Removed pipeline notifications: {$pipeNotif}." . PHP_EOL;
    echo "Cleared activity log rows: {$activity}." . PHP_EOL;
    echo "Removed department user accounts: {$nDept}. Administrators kept: {$nAdmin}." . PHP_EOL;
    echo 'Remaining users:' . PHP_EOL;
    foreach ($conn->query('SELECT id, username, full_name, role FROM users ORDER BY id') as $u) {
        echo '  #' . $u['id'] . ' ' . $u['username'] . ' (' . $u['role'] . ') — ' . $u['full_name'] . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite($isCli ? STDERR : STDOUT, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
