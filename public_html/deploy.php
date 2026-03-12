<?php
/**
 * GIT DEPLOYMENT SCRIPT
 *
 * Automatically deploy all folders in the repo via GitHub
 */

// List of folders to deploy
$folders = ['public_html', 'collector', 'reporting', 'experiment'];

$output = '';

foreach ($folders as $folder) {
    $commands = [
        "echo 'Deploying $folder...'",
        "cd $folder",
        "git pull",
        "git status",
        "git submodule sync",
        "git submodule update",
        "git submodule status",
        "cd .."
    ];

    foreach ($commands as $command) {
        $tmp = shell_exec($command);
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
</body>
</html>
