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
function setup_apache($base, $dest)
{
    if (
        empty($_SERVER['SERVER_SOFTWARE']) ||
        stripos($_SERVER['SERVER_SOFTWARE'], 'apache') === false
    ) {
        echo 'Warning: Use this script only with Apache', PHP_EOL;
        exit;
    }

    if ($base === '/' || $base === '\\') {
        $base = '';
    }

    $base = $base . '/index.php/RESERVED.INPHINIT-';

    $data = '<IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    IndexIgnore *

    # Redirect page errors to route system
    ErrorDocument 403 ' . $base . '403.html
    ErrorDocument 500 ' . $base . '500.html
    ErrorDocument 501 ' . $base . '501.html

    RewriteEngine On

    # Ignore hidden files
    RewriteRule ^\.|/\. index.php [L]

    # Redirect to public folder
    RewriteCond %{REQUEST_URI} !(^$|public/|index\.php(/|$))
    RewriteRule ^(.*)$ public/$1

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
function setup_iis($base, $dest)
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
                <remove statusCode="403" subStatusCode="-1" />
                <remove statusCode="500" subStatusCode="-1" />
                <remove statusCode="501" subStatusCode="-1" />
                <error statusCode="403"
                       responseMode="ExecuteURL"
                       path="' . $base . '403.html?RESERVED_IISREDIRECT=1" />
                <error statusCode="500"
                       responseMode="ExecuteURL"
                       path="' . $base . '501.html?RESERVED_IISREDIRECT=1" />
                <error statusCode="501"
                       responseMode="ExecuteURL"
                       path="' . $base . '501.html?RESERVED_IISREDIRECT=1" />
            </httpErrors>
            <rewrite>
                <rules>
                    <rule name="Ignore hidden files" stopProcessing="true">
                        <match url="(^\.|/\.)" />
                        <action type="Rewrite" url="index.php" />
                    </rule>
                    <rule name="Redirect to public folder" stopProcessing="false">
                        <match url="^(.*)" />
                        <action type="Rewrite" url="public/{R:1}" />
                    </rule>
                    <rule name="Redirect all urls to index.php if no exits files" stopProcessing="true">
                        <conditions>
                            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
                        </conditions>
                        <match url="^public/" />
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
 * Display a example for nginx.conf
 *
 * @param string $base    Define full path
 * @param string $fastcgi Define fastcgi pass, eg.: unix:php-fpm.sock, 127.0.0.1:9000
 * @return void
 */
function setup_nginx($base, $fastcgi)
{
    $base = realpath($base);

    if ($base === false) {
        echo 'Warning: Invaid root path for NGINX';
    } else {
        $base = rtrim(strtr($base, '\\', '/'), '/');

        $data = '
        location / {
            root ' . $base . ';

            # Redirect page errors to route system
            error_page 403 /index.php/RESERVED.TEENY-403.html;
            error_page 500 /index.php/RESERVED.TEENY-500.html;
            error_page 501 /index.php/RESERVED.TEENY-501.html;

            try_files /public$uri /index.php?$query_string;

            location = / {
                try_files $uri /index.php?$query_string;
            }

            location ~ /\. {
                try_files /index.php$uri /index.php?$query_string;
            }

            location ~ \.php$ {
                # Replace by your FPM or FastCGI
                fastcgi_pass ' . $fastcgi . ';

                fastcgi_index index.php;
                include fastcgi_params;

                set $teeny_suffix "";

                if ($uri != "/index.php") {
                    set $teeny_suffix "/public";
                }

                fastcgi_param SCRIPT_FILENAME $realpath_root$teeny_suffix$fastcgi_script_name;
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
