<?php

function rglob($pattern = '*', $path = '', $flags = 0)
{
    $paths = glob($path.'*', GLOB_MARK | GLOB_ONLYDIR | GLOB_NOSORT);
    $files = glob($path.$pattern, $flags);
    foreach ($paths as $path) {
        $files = array_merge($files, rglob($pattern, $path, $flags));
    }

    return $files;
}

function getTemplates()
{
    $files = rglob('*.html', PATH_INSTALL.'/install_files/template');
    $replaces = [PATH_INSTALL.'/install_files/', DIRECTORY_SEPARATOR, '.html'];

    foreach ($files as $file) {
        $template = new StdClass();
        $template->id = str_replace($replaces, ['', '_', ''], $file);
        $template->path = $file;
        $templates[] = $template;
    }

    return $templates;
}

function rchmod($path, $filePerm = 0644, $dirPerm = 0755)
{
    if (! file_exists($path)) {
        return (false);
    }

    if (is_file($path)) {
        chmod($path, $filePerm);
    } elseif (is_dir($path)) {
        $foldersAndFiles = scandir($path);

        $entries = array_slice($foldersAndFiles, 2);

        foreach ($entries as $entry) {
            rchmod($path.DIRECTORY_SEPARATOR.$entry, $filePerm, $dirPerm);
        }
        chmod($path, $dirPerm);
    }

    return (true);
}
