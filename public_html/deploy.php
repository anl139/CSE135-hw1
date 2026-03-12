<?php
/**
 * GIT DEPLOYMENT SCRIPT
 *
 * Automatically deploy all folders in the repo via GitHub
 */

// Base directory of your repo
$baseDir = '/var/www/anl139.site';

// List of folders to deploy
$folders = ['public_html', 'collector', 'reporting', 'experiment'];

$output = '';

foreach ($folders as $folder) {
    $path = "$baseDir/$folder"; // absolute path to folder

    $commands = [
        "echo 'Deploying $folder...'",
        "cd $path && git pull",
        "cd $path && git status",
        "cd $path && git submodule sync",
        "cd $path && git submodule update",
        "cd $path && git submodule status",
    ];

    foreach ($commands as $command) {
        $tmp = shell_exec($command . ' 2>&1'); // capture stderr too
        $output .= "<span style=\"color: #6BE234;\">\$</span><span style=\"color: #729FCF;\"> {$command}</span><br />";
        $output .= htmlentities(trim($tmp)) . "<br /><br />";
    }
}
?>

<!DOCTYPE HTML>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <title>GIT DEPLOYMENT SCRIPT</title>
</head>
<body style="background-color: #000; color: #fff; font-weight: bold; padding: 10px;">
    <h2>Git Deployment Script Output</h2>
    <?php echo $output; ?>
</body>
</html>
