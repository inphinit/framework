<?php
/*
 * Inphinit
 *
 * Copyright (c) 2024 Guilherme Nascimento (brcontainer@yahoo.com.br)
 *
 * Released under the MIT license
 */

/**
 * Generate .htaccess
 *
 * @param string $base Define path equivalent "HTTP path"
 * @param string $dest Location to write .htaccess
 * @return void
 */
function SetupApache($base, $dest)
{
    if (
        empty($_SERVER['SERVER_SOFTWARE']) ||
        stripos($_SERVER['SERVER_SOFTWARE'], 'apache') === false
    ) {
        echo 'Warning: Use this script only with Apache', PHP_EOL;
        exit;
    }

    $base = $base . '/index.php/RESERVED.INPHINIT-';

    $data = '<IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    IndexIgnore *

    # Redirect page errors to route system
    ErrorDocument 401 ' . $base . '401.html
    ErrorDocument 403 ' . $base . '403.html
    ErrorDocument 500 ' . $base . '500.html
    ErrorDocument 501 ' . $base . '501.html

    RewriteEngine On

    # Redirect to public folder
    RewriteCond %{REQUEST_URI} !(^$|system/public/|index\.php(/|$))
    RewriteRule ^(.*)$ system/public/$1

    # Redirect all urls to index.php if no exits files
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
    ';

    $data = str_replace("\n    ", "\n", $data);

    file_put_contents($dest . '/.htaccess', $data);

    echo '<pre>', htmlspecialchars($data), '</pre>';
}

/**
 * Generate web.config for IIS
 *
 * @param string $base Define path equivalent "HTTP path"
 * @param string $dest Location to write web.config
 * @return void
 */
function SetupIIS($base, $dest)
{
    if (
        empty($_SERVER['SERVER_SOFTWARE']) ||
        stripos($_SERVER['SERVER_SOFTWARE'], 'microsoft-iis') === false
    ) {
        echo 'Warning: Use this script only with IIS or IIS Express', PHP_EOL;
        exit;
    }

    $base = $base . '/index.php/RESERVED.INPHINIT-';

    $data = '<?xml version="1.0" encoding="UTF-8"?>
    <configuration>
        <system.webServer>
            <directoryBrowse enabled="false" />
            <defaultDocument>
                <files>
                    <clear />
                    <add value="index.php" />
                </files>
            </defaultDocument>
            <httpErrors>
                <remove statusCode="401" subStatusCode="-1" />
                <remove statusCode="403" subStatusCode="-1" />
                <remove statusCode="501" subStatusCode="-1" />
                <error statusCode="401"
                       responseMode="ExecuteURL"
                       path="' . $base . '401.html?RESERVED_IISREDIRECT=1" />
                <error statusCode="403"
                       responseMode="ExecuteURL"
                       path="' . $base . '403.html?RESERVED_IISREDIRECT=1" />
                <error statusCode="501"
                       responseMode="ExecuteURL"
                       path="' . $base . '501.html?RESERVED_IISREDIRECT=1" />
            </httpErrors>
            <rewrite>
                <rules>
                    <rule name="Redirect to public folder" stopProcessing="false">
                        <match url="^(.*)" />
                        <action type="Rewrite" url="system/public/{R:1}" />
                    </rule>
                    <rule name="Redirect all urls to index.php if no exits files" stopProcessing="true">
                        <conditions>
                            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        </conditions>
                        <match url="^system/public/" />
                        <action type="Rewrite" url="index.php" />
                    </rule>
                </rules>
            </rewrite>
        </system.webServer>
    </configuration>
    ';

    $data = str_replace("\n    ", "\n", $data);

    file_put_contents($dest . '/web.config', $data);

    echo '<pre>', htmlspecialchars($data), '</pre>';
}

/**
 * Generate configs to nginx.conf and setup extensions used (eg. hh for HHVM)
 *
 * @param string $base       Define full path
 * @param array  $extensions Define extensions used by server
 * @return void
 */
function SetupNginx($base, array $extensions)
{
    $base = realpath($base);

    if ($base === false) {
        echo 'Warning: Invaid root path for Nginx';
    } else {
        $base = rtrim(strtr($base, '\\', '/'), '/');

        $exts = implode('|', $extensions);

        if (count($extensions) > 1) {
            $exts = '(' . $exts . ')';
        }

        $data = '
        location / {
            root  ' . $base . ';
            index index.html index.htm index.php;

            # Redirect page errors to route system
            error_page 401 /index.php/RESERVED.INPHINIT-401.html;
            error_page 403 /index.php/RESERVED.INPHINIT-403.html;
            error_page 500 /index.php/RESERVED.INPHINIT-500.html;
            error_page 501 /index.php/RESERVED.INPHINIT-501.html;

            try_files /system/public/$uri /index.php?$query_string;

            location ~ \.' . $exts . '$ {
                include       fastcgi_params;
                fastcgi_index index.php;
                fastcgi_param INPHINIT_ROOT   $document_root
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                fastcgi_param SCRIPT_NAME     $fastcgi_script_name;
                fastcgi_pass  127.0.0.1:9000; # Replace by your fastcgi
            }
        }
        ';

        $data = str_replace("\n        ", "\n", $data);

        if (PHP_SAPI !== 'cli') {
            echo '<pre>', htmlspecialchars($data), '</pre>';
        } else {
            echo $data;
        }
    }
}
