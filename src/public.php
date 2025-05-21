<?php
/*
 * Inphinit
 *
 * Copyright (c) 2025 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

use Inphinit\Filesystem\File;

if (
    INPHINIT_PATH === '/' ||
    strpos(INPHINIT_PATH, '.') === 0 ||
    strpos(INPHINIT_PATH, '/.') !== false ||
    is_file($_SERVER['DOCUMENT_ROOT'] . INPHINIT_PATH) === false
) {
    return false;
}

$inphinit_type_from_extension = [
    'application/arj' => ['arj'],
    'application/atom+xml' => ['atom'],
    'application/json' => ['json'],
    'application/msword' => ['doc'],
    'application/pdf' => ['pdf'],
    'application/rss+xml' => ['rss'],
    'application/rtf' => ['rtf'],
    'application/vnd.ms-excel' => ['xls'],
    'application/vnd.ms-powerpoint' => ['ppt'],
    'application/vnd.oasis.opendocument.database' => ['odb'],
    'application/vnd.oasis.opendocument.presentation' => ['odp'],
    'application/vnd.oasis.opendocument.spreadsheet' => ['ods'],
    'application/vnd.oasis.opendocument.text' => ['odt'],
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
    'application/x-font-otf' => ['otf'],
    'application/x-font-ttf' => ['ttf'],
    'application/x-freearc' => ['arc'],
    'application/x-msaccess' => ['accdb', 'mdb'],
    'application/x-shockwave-flash' => ['swf'],
    'application/xml' => ['xml'],
    'audio/amr' => ['amr'],
    'audio/midi' => ['midi'],
    'audio/mpeg' => ['mp3'],
    'audio/ogg' => ['ogg'],
    'audio/x-aac' => ['aac'],
    'audio/x-flac' => ['flac'],
    'audio/x-mpegurl' => ['m3u'],
    'audio/x-ms-wma' => ['wma'],
    'audio/x-wav' => ['wav'],
    'image/apng' => ['apng'],
    'image/avif' => ['avif'],
    'image/bmp' => ['bmp'],
    'image/gif' => ['gif'],
    'image/jpeg' => ['jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp'],
    'image/png' => ['png', 'apng'],
    'image/svg+xml' => ['svg'],
    'image/tiff' => ['tiff'],
    'image/vnd.adobe.photoshop' => ['psd'],
    'image/webp' => ['webp'],
    'image/x-icon' => ['ico'],
    'text/csv' => ['csv'],
    'text/html' => ['html', 'htm'],
    'text/markdown' => ['md'],
    'text/plain' => ['txt', 'reg'],
    'text/tab-separated-values' => ['tsv'],
    'text/yaml' => ['yaml'],
    'video/3gpp' => ['3gp'],
    'video/mp4' => ['mp4'],
    'video/mpeg' => ['mpeg'],
    'video/quicktime' => ['mov'],
    'video/webm' => ['webm'],
    'video/x-flv' => ['flv'],
    'video/x-m4v' => ['m4v'],
    'video/x-matroska' => ['mkv'],
    'video/x-ms-vob' => ['vob'],
    'video/x-ms-wmv' => ['wmv'],
    'video/x-msvideo' => ['avi']
];

$inphinit_path_extension = pathinfo(INPHINIT_PATH, PATHINFO_EXTENSION);

foreach ($inphinit_type_from_extension as $mime => $extensions) {
    if (in_array($inphinit_path_extension, $extensions)) {
        header('Content-Type: ' . $mime, true);
        File::output($_SERVER['DOCUMENT_ROOT'] . INPHINIT_PATH);
        exit;
    }
}

return true;
